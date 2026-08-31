<?php
declare(strict_types=1);

namespace Mengo\IdApproval\Services;

use Mengo\IdApproval\Models\User;
use Mengo\IdApproval\Repositories\AuditLogRepository;
use Mengo\IdApproval\Security\SessionManager;

class AuditService
{
    private AuditLogRepository $auditRepo;

    public function __construct(?AuditLogRepository $auditRepo = null)
    {
        $this->auditRepo = $auditRepo ?? new AuditLogRepository();
    }

    public function logWorkflow(
        ?int $idCardId,
        string $action,
        ?string $previousStatus = null,
        ?string $newStatus = null,
        ?int $versionNumber = null,
        string $details = '',
        ?string $ip = null,
        ?string $userAgent = null
    ): int {
        $currentUser = SessionManager::getUser();
        $cardId = ($idCardId !== null && $idCardId > 0) ? $idCardId : null;
        $userId = ($currentUser && !empty($currentUser['id']) && (int)$currentUser['id'] > 0) ? (int)$currentUser['id'] : null;

        return $this->auditRepo->log([
            'id_card_id' => $cardId,
            'user_id' => $userId,
            'user_name' => $currentUser ? (string)$currentUser['name'] : 'System',
            'user_role' => $currentUser ? (string)$currentUser['role'] : 'SYSTEM',
            'action' => $action,
            'entity_type' => $cardId ? 'ID_CARD' : 'SYSTEM',
            'entity_id' => $cardId,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'version_number' => $versionNumber,
            'ip_address' => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'user_agent' => $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/Internal'),
            'details' => $details
        ]);
    }

    public function logUserAction(
        User $user,
        string $action,
        string $details,
        ?string $ip = null,
        ?string $userAgent = null
    ): int {
        return $this->auditRepo->log([
            'id_card_id' => null,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => $action,
            'entity_type' => 'USER',
            'entity_id' => $user->id,
            'ip_address' => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'user_agent' => $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/Internal'),
            'details' => $details
        ]);
    }

    public function logSecurity(
        string $action,
        string $details,
        ?string $ip = null,
        ?string $userAgent = null
    ): int {
        return $this->auditRepo->log([
            'id_card_id' => null,
            'user_id' => null,
            'user_name' => 'Anonymous / Security Monitor',
            'user_role' => 'SYSTEM',
            'action' => $action,
            'entity_type' => 'SECURITY_EVENT',
            'ip_address' => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'user_agent' => $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/Internal'),
            'details' => $details
        ]);
    }

    public function log(array $data): int
    {
        return $this->auditRepo->log($data);
    }
}
