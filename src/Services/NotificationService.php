<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Services;

use Mengo\IdApproval\Models\IdCard;
use Mengo\IdApproval\Models\Notification;
use Mengo\IdApproval\Models\Role;
use Mengo\IdApproval\Repositories\NotificationOutboxRepository;
use Mengo\IdApproval\Repositories\NotificationRepository;
use Mengo\IdApproval\Repositories\UserRepository;
use Mengo\IdApproval\Support\Timezone;

/**
 * NotificationService
 *
 * Manages all workflow-triggered notifications:
 *  - In-app notifications (written synchronously to `notifications` table — fast DB inserts).
 *  - Email notifications (written to `notification_outbox` table inside the same transaction,
 *    then delivered asynchronously by the CLI worker `scripts/process_outbox.php`).
 *
 * This pattern decouples SMTP latency from workflow performance and eliminates the
 * risk of a mail server failure rolling back or blocking an HR approval transaction.
 */
class NotificationService
{
    private NotificationRepository $notifRepo;
    private UserRepository $userRepo;
    private NotificationOutboxRepository $outboxRepo;

    /**
     * Request-scoped user-by-role cache to eliminate N+1 DB queries within a single request.
     * @var array<string, array<int, object>>
     */
    private array $roleCache = [];

    public function __construct(
        ?NotificationRepository $notifRepo = null,
        ?UserRepository $userRepo = null,
        ?NotificationOutboxRepository $outboxRepo = null
    ) {
        $this->notifRepo  = $notifRepo  ?? new NotificationRepository();
        $this->userRepo   = $userRepo   ?? new UserRepository();
        $this->outboxRepo = $outboxRepo ?? new NotificationOutboxRepository();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ROLE CACHE — eliminates repeated DB hits for the same role within a request
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Lazily load and cache all users for a given role.
     * @return object[]
     */
    private function getUsersByRole(string $role): array
    {
        if (!isset($this->roleCache[$role])) {
            $this->roleCache[$role] = $this->userRepo->findByRole($role);
        }
        return $this->roleCache[$role];
    }

    /**
     * Extract non-empty email addresses from a set of users.
     * @return string[]
     */
    private function emailsForRole(string $role): array
    {
        return array_values(array_filter(
            array_map(fn($u) => $u->email ?? '', $this->getUsersByRole($role))
        ));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // OUTBOX HELPER
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Queue an email notification in the outbox.
     * This is a non-blocking DB insert; actual delivery happens asynchronously.
     *
     * @param string|string[] $toEmails
     * @param array<string,mixed>|null $details
     */
    private function queueEmail(
        string $eventType,
        string|array $toEmails,
        string $subject,
        string $headline,
        string $bodyText,
        ?array $details = null,
        ?int $idCardId = null
    ): void {
        $emails = is_array($toEmails) ? $toEmails : [$toEmails];
        $emails = array_values(array_filter($emails));
        if (empty($emails)) {
            return; // No recipients — nothing to queue
        }

        $this->outboxRepo->create([
            'event_type'  => $eventType,
            'to_emails'   => $emails,
            'subject'     => $subject,
            'headline'    => $headline,
            'body_text'   => $bodyText,
            'details_json' => $details,
            'id_card_id'  => $idCardId,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // WORKFLOW NOTIFICATION METHODS
    // ──────────────────────────────────────────────────────────────────────────

    public function notifyIdUploaded(IdCard $card, string $designerName): void
    {
        $empName = $card->employee_name ?? 'Employee';
        $ref     = $card->card_reference;

        // 1. Synchronous in-app notification for all HR Managers
        $this->notifRepo->create([
            'role_target' => Role::HR_MANAGER,
            'type'        => Notification::TYPE_ID_UPLOADED,
            'title'       => "New ID Pending Approval: {$empName}",
            'message'     => "Designer {$designerName} uploaded ID card for {$empName} ({$ref}). Requires HR review.",
            'id_card_id'  => $card->id,
            'link_url'    => "/id-cards/{$card->id}",
        ]);

        // 2. Async email via outbox
        $hrEmails = $this->emailsForRole(Role::HR_MANAGER);
        $this->queueEmail(
            'ID_UPLOADED',
            $hrEmails,
            "ID Submission Requires HR Review: {$empName}",
            "New Employee ID Submission Pending Review",
            "Designer {$designerName} has submitted a new ID card for {$empName} requiring HR Manager review and approval.",
            [
                'Employee Name'  => $empName,
                'Card Reference' => $ref,
                'Submitted By'   => $designerName,
                'Current Status' => 'PENDING_HR_APPROVAL',
            ],
            $card->id
        );
    }

    public function notifyCorrectionRequested(IdCard $card, string $hrName, string $reason, ?int $designerUserId): void
    {
        $empName = $card->employee_name ?? 'Employee';
        $ref     = $card->card_reference;
        $now     = Timezone::formatDetailed(Timezone::nowString());

        // 1. In-app notification targeted to the specific designer
        $this->notifRepo->create([
            'user_id'     => $designerUserId,
            'role_target' => Role::DESIGNER,
            'type'        => Notification::TYPE_CORRECTION_REQUESTED,
            'title'       => "Correction Requested: {$empName} ({$ref})",
            'message'     => "HR Manager {$hrName} requested a correction on {$now}.\nReason: \"{$reason}\"",
            'id_card_id'  => $card->id,
            'link_url'    => "/id-cards/{$card->id}",
        ]);

        // 2. Async email to the designer via outbox
        if ($designerUserId) {
            $designerUser = $this->userRepo->findById($designerUserId);
            if ($designerUser && !empty($designerUser->email)) {
                $this->queueEmail(
                    'CORRECTION_REQUESTED',
                    $designerUser->email,
                    "Action Required: Correction Requested for {$empName}",
                    "ID Card Design Correction Requested",
                    "HR Manager {$hrName} has reviewed the ID card submission for {$empName} and requested modifications before approval.",
                    [
                        'Employee Name'      => $empName,
                        'Card Reference'     => $ref,
                        'HR Manager'         => $hrName,
                        'Correction Remarks' => $reason,
                        'Current Status'     => 'CORRECTION_REQUESTED',
                    ],
                    $card->id
                );
            }
        }
    }

    public function notifyIdReuploaded(IdCard $card, string $designerName, int $versionNumber): void
    {
        $empName = $card->employee_name ?? 'Employee';
        $ref     = $card->card_reference;

        // 1. In-app notification for all HR Managers
        $this->notifRepo->create([
            'role_target' => Role::HR_MANAGER,
            'type'        => Notification::TYPE_ID_REUPLOADED,
            'title'       => "Corrected ID Re-uploaded (v{$versionNumber}): {$empName}",
            'message'     => "Designer {$designerName} has uploaded corrected version v{$versionNumber} for {$empName} ({$ref}). Ready for re-review.",
            'id_card_id'  => $card->id,
            'link_url'    => "/id-cards/{$card->id}",
        ]);

        // 2. Async email via outbox
        $hrEmails = $this->emailsForRole(Role::HR_MANAGER);
        $this->queueEmail(
            'ID_REUPLOADED',
            $hrEmails,
            "Corrected ID Re-submitted for Review: {$empName}",
            "Corrected Employee ID v{$versionNumber} Re-submitted",
            "Designer {$designerName} has uploaded a corrected PDF (v{$versionNumber}) for {$empName} in response to HR feedback.",
            [
                'Employee Name'  => $empName,
                'Card Reference' => $ref,
                'Updated Version'=> "v{$versionNumber}",
                'Designer'       => $designerName,
                'Current Status' => 'PENDING_HR_APPROVAL',
            ],
            $card->id
        );
    }

    public function notifyIdApproved(IdCard $card, string $hrName, int $versionNumber, ?int $designerUserId): void
    {
        $empName = $card->employee_name ?? 'Employee';
        $ref     = $card->card_reference;
        $now     = Timezone::formatDetailed(Timezone::nowString());

        // 1a. In-app notification to designer
        if ($designerUserId) {
            $this->notifRepo->create([
                'user_id'    => $designerUserId,
                'type'       => Notification::TYPE_ID_APPROVED,
                'title'      => "ID Card Approved: {$empName}",
                'message'    => "Your ID design (v{$versionNumber}) for {$empName} ({$ref}) was approved by {$hrName} on {$now}.",
                'id_card_id' => $card->id,
                'link_url'   => "/id-cards/{$card->id}",
            ]);

            // 1b. Async email to designer
            $designerUser = $this->userRepo->findById($designerUserId);
            if ($designerUser && !empty($designerUser->email)) {
                $this->queueEmail(
                    'ID_APPROVED',
                    $designerUser->email,
                    "ID Card Approved: {$empName}",
                    "Employee ID Card Design Approved",
                    "Your submitted ID card design (v{$versionNumber}) for {$empName} has been verified and approved by HR Manager {$hrName}.",
                    [
                        'Employee Name'   => $empName,
                        'Approved Version'=> "v{$versionNumber}",
                        'Approved By'     => $hrName,
                        'Approval Date'   => $now,
                    ],
                    $card->id
                );
            }
        }

        // 2a. In-app notification to Printing Officers
        $this->notifRepo->create([
            'role_target' => Role::PRINTING_OFFICER,
            'type'        => Notification::TYPE_ID_READY_FOR_PRINTING,
            'title'       => "New ID Ready for Printing: {$empName}",
            'message'     => "Employee ID for {$empName} ({$ref}, v{$versionNumber}) was approved by {$hrName} on {$now} and is ready for production.",
            'id_card_id'  => $card->id,
            'link_url'    => "/printing/ready",
        ]);

        // 2b. Async email to Printing Officers
        $printerEmails = $this->emailsForRole(Role::PRINTING_OFFICER);
        $this->queueEmail(
            'ID_READY_FOR_PRINTING',
            $printerEmails,
            "New Approved ID Ready for Printing: {$empName}",
            "ID Card Cleared for Production Queue",
            "Employee ID for {$empName} (v{$versionNumber}) has been approved by HR Manager {$hrName} and is now queued for physical printing.",
            [
                'Employee Name'   => $empName,
                'Card Reference'  => $ref,
                'Approved Version'=> "v{$versionNumber}",
                'Approved By'     => $hrName,
                'Current Status'  => 'APPROVED (Ready for Printing)',
            ],
            $card->id
        );
    }

    public function notifyIdPrinted(IdCard $card, string $printingOfficerName): void
    {
        $empName = $card->employee_name ?? 'Employee';
        $ref     = $card->card_reference;
        $now     = Timezone::formatDetailed(Timezone::nowString());

        // 1. In-app notification to HR Managers
        $this->notifRepo->create([
            'role_target' => Role::HR_MANAGER,
            'type'        => Notification::TYPE_ID_PRINTED,
            'title'       => "ID Printed & Ready for Collection: {$empName}",
            'message'     => "Physical ID card for {$empName} ({$ref}) was printed by Printing Officer {$printingOfficerName} on {$now}. Card is awaiting collection.",
            'id_card_id'  => $card->id,
            'link_url'    => "/id-cards/{$card->id}",
        ]);

        // 2. Async email via outbox
        $hrEmails = $this->emailsForRole(Role::HR_MANAGER);
        $this->queueEmail(
            'ID_PRINTED',
            $hrEmails,
            "ID Card Printed & Ready for Collection: {$empName}",
            "Physical ID Card Production Completed",
            "Physical ID card printing for {$empName} has been completed by Printing Officer {$printingOfficerName}. Card is now available at the HR Collection Center.",
            [
                'Employee Name'  => $empName,
                'Card Reference' => $ref,
                'Printed By'     => $printingOfficerName,
                'Printed Date'   => $now,
                'Current Status' => 'PRINTED (Awaiting Collection)',
            ],
            $card->id
        );
    }

    public function notifyIdCollected(IdCard $card, string $hrName, string $recipientName): void
    {
        $empName = $card->employee_name ?? 'Employee';
        $ref     = $card->card_reference;
        $now     = Timezone::formatDetailed(Timezone::nowString());

        // In-app notification (collection events rarely need an email)
        $this->notifRepo->create([
            'role_target' => Role::HR_MANAGER,
            'type'        => Notification::TYPE_ID_COLLECTED,
            'title'       => "ID Card Collected: {$empName}",
            'message'     => "Physical ID for {$empName} ({$ref}) was collected by {$recipientName} and verified by {$hrName} on {$now}.",
            'id_card_id'  => $card->id,
            'link_url'    => "/id-cards/{$card->id}",
        ]);
    }
}
