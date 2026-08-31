<?php
use Mengo\IdApproval\Security\CsrfToken;
?>

<div style="margin-bottom: 24px; text-align: center; background-color: #0EA5E9; border-radius: 35px;">
  <h2 style="font-size: 35px; font-weight: 900; color: #F00000; margin-bottom: 4px;"> WELCOME BACK </h2>
  <p style="font-size: 13px; font-weight: 900; color: #1F2937; line-height: 2;">
    Sign in to continue to the secure<br> <b>Mengo Hospital ID Management Portal.</b>
    <br>
    <br>
  </p>
</div>

<form action="/login" method="POST" id="loginForm">
  <?= CsrfToken::field() ?>

  <div class="form-group" style="margin-bottom: 18px;">
    <label class="form-label" for="username" style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Username</label>
    <div style="position: relative;">
      <input 
        type="text" 
        id="username" 
        name="username" 
        class="form-control" 
        required 
        placeholder="Enter your username" 
        autofocus 
        autocomplete="username"
        style="padding-left: 38px; height: 44px; border-radius: 35px; border: 4px solid #cbd5e1; font-size: 14px; width: 100%; transition: all 0.2s ease;"
      >
      <i class="fa-solid fa-user" style="position: absolute; left: 13px; top: 14px; color: #94a3b8; font-size: 14px;"></i>
    </div>
  </div>

  <div class="form-group" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
      <label class="form-label" for="password" style="font-size: 13px; font-weight: 700; color: #334155; margin: 0;">Password</label>
    </div>
    <div style="position: relative;">
      <input 
        type="password" 
        id="password" 
        name="password" 
        class="form-control" 
        required 
        placeholder="Enter your password" 
        autocomplete="current-password"
        style="padding-left: 38px; padding-right: 42px; height: 44px; border-radius: 38px; border: 4px solid #001F3F; font-size: 14px; width: 100%; transition: all 0.2s ease;"
      >
      <i class="fa-solid fa-lock" style="position: absolute; border-radius: 35px; left: 13px; top: 14px; color: #94a3b8; font-size: 14px;"></i>
      
      <button 
        type="button" 
        id="togglePasswordBtn" 
        aria-label="Toggle password visibility"
        onclick="togglePasswordVisibility()"
        style="position: absolute; right: 4px; top: 4px; bottom: 4px; width: 36px; background: transparent; border: none; color: #64748b; cursor: pointer; border-radius: 6px; display: flex; align-items: center; justify-content: center; transition: color 0.15s ease;"
        title="Show / Hide Password"
      >
        <i class="fa-solid fa-eye" id="togglePasswordIcon" style="font-size: 14px;"></i>
      </button>
    </div>
  </div>

  <div style="margin-top: 28px;">
    <button 
      type="submit" 
      class="btn btn-primary" 
      style="width: 100%; height: 46px;  border-radius: 58px; font-size: 14px; font-weight: 700; background: #001F3F; border: 4px solid #800000; box-shadow: 0 4px 12px rgba(153, 0, 0, 0.3); cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 8px; color: #ffffff;"
    >
      <i class="fa-solid fa-right-to-bracket"></i> SIGN IN TO ACCESS PORTAL
    </button>
  </div>
</form>

<script>
  function togglePasswordVisibility() {
    const input = document.getElementById('password');
    const icon = document.getElementById('togglePasswordIcon');
    if (!input || !icon) return;

    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.remove('fa-eye');
      icon.classList.add('fa-eye-slash');
      icon.style.color = '#0EA5E9';
    } else {
      input.type = 'password';
      icon.classList.remove('fa-eye-slash');
      icon.classList.add('fa-eye');
      icon.style.color = '#64748b';
    }
  }
</script>
