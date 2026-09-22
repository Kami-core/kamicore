<!-- kami:template auth_ui -->
<div class="kc-auth" data-auth>
    <div class="kc-auth-tabs" role="tablist" aria-label="{{phrase.login}} / {{phrase.register}}">
        <button class="kc-auth-tab is-active"
                type="button"
                role="tab"
                aria-selected="true"
                data-auth-mode="login">
            {{phrase.login}}
        </button>
        <button class="kc-auth-tab"
                type="button"
                role="tab"
                aria-selected="false"
                tabindex="-1"
                data-auth-mode="register">
            {{phrase.register}}
        </button>
    </div>

    <section data-auth-panel="login">
        <h2 id="login-modal-title" class="kc-modal-title">{{phrase.login}}</h2>

        <form action="UserAccount/login"
              method="post"
              data-ajax-form
              data-target="#login-result">
            <div class="kc-form-group">
                <label class="kc-form-label" for="account-login">
                    {{phrase.username_or_email}}
                </label>
                <input id="account-login"
                       class="kc-input"
                       name="login"
                       type="text"
                       placeholder="{{phrase.username_or_email}}"
                       autocomplete="username"
                       autofocus
                       required>
                <div class="kc-form-helper">{{phrase.login_help}}</div>
            </div>

            <div class="kc-form-group">
                <label class="kc-form-label" for="account-password">
                    {{phrase.password}}
                </label>
                <input id="account-password"
                       class="kc-input"
                       name="password"
                       type="password"
                       placeholder="{{phrase.password}}"
                       autocomplete="current-password"
                       required>
                <div class="kc-form-helper">{{phrase.password_case_sensitive}}</div>
            </div>

            <label class="kc-checkbox-label">
                <input class="kc-checkbox" name="remember" type="checkbox" value="1">
                <span>{{phrase.remember_me}}</span>
            </label>

            <button class="kc-btn kc-btn-primary kc-width-full" type="submit">
                {{phrase.login}}
            </button>
        </form>

        <button class="kc-btn kc-btn-link kc-width-full" style="margin-top:12px" type="button" data-auth-mode="password-reset">
            {{phrase.forgot_password}}
        </button>
    </section>

    <section data-auth-panel="password-reset" hidden>
        <h2 class="kc-modal-title">{{phrase.reset_password}}</h2>
        <p class="kc-form-helper">{{phrase.password_reset_request_help}}</p>

        <form action="UserAccount/request_password_reset"
              method="post"
              data-ajax-form
              data-target="#login-result">
            <div class="kc-form-group">
                <label class="kc-form-label" for="account-reset-identity">
                    {{phrase.username_or_email}}
                </label>
                <input id="account-reset-identity"
                       class="kc-input"
                       name="identity"
                       type="text"
                       placeholder="{{phrase.username_or_email}}"
                       autocomplete="username"
                       required>
            </div>

            <button class="kc-btn kc-btn-primary kc-width-full" type="submit">
                {{phrase.send_password_reset_link}}
            </button>
        </form>

        <button class="kc-btn kc-btn-link kc-width-full" style="margin-top:12px" type="button" data-auth-mode="login">
            {{phrase.back_to_login}}
        </button>
    </section>

    <section data-auth-panel="register" hidden>
        <h2 class="kc-modal-title">{{phrase.register}}</h2>

        <form action="UserAccount/register"
              method="post"
              data-ajax-form
              data-target="#login-result">
            <div class="kc-form-group">
                <label class="kc-form-label" for="account-username">{{phrase.username}}</label>
                <input id="account-username"
                       class="kc-input"
                       name="username"
                       type="text"
                       placeholder="{{phrase.username}}"
                       autocomplete="username"
                       required>
            </div>

            <div class="kc-form-group">
                <label class="kc-form-label" for="account-email">{{phrase.email}}</label>
                <input id="account-email"
                       class="kc-input"
                       name="useremail"
                       type="email"
                       placeholder="{{phrase.email}}"
                       autocomplete="email"
                       required>
            </div>

            <div class="kc-form-group">
                <label class="kc-form-label" for="account-new-password">{{phrase.password}}</label>
                <input id="account-new-password"
                       class="kc-input"
                       name="userpassword"
                       type="password"
                       placeholder="{{phrase.password}}"
                       autocomplete="new-password"
                       required>
            </div>

            <div class="kc-form-group">
                <label class="kc-form-label" for="account-repeat-password">{{phrase.repeat_password}}</label>
                <input id="account-repeat-password"
                       class="kc-input"
                       name="userpassword_repeat"
                       type="password"
                       placeholder="{{phrase.repeat_password}}"
                       autocomplete="new-password"
                       required>
            </div>

            <button class="kc-btn kc-btn-primary kc-width-full" type="submit">
                {{phrase.register}}
            </button>
        </form>
    </section>

    {{google_auth}}

    <div id="login-result" class="kc-auth-result" aria-live="polite"></div>
</div>
<!-- /kami:template -->

<!-- kami:template auth_authorized -->
<div class="kc-auth">
    <div class="kc-notice" role="status">
        {{phrase.already_signed_in_as}} <strong>{{username}}</strong>.
    </div>
    <a class="kc-btn kc-btn-secondary kc-width-full"
       href="{{logout_url}}"
       style="margin-top:12px">
        {{phrase.logout}}
    </a>
</div>
<!-- /kami:template -->

<!-- kami:template google_auth_button -->
<div class="kc-auth-divider"></div>

<a class="kc-btn kc-btn-secondary kc-width-full" href="{{google_url}}">
    {{phrase.continue_with_google}}
</a>
<!-- /kami:template -->

<!-- kami:template login_errors -->
<div class="kc-notice kc-notice-error" role="alert">{{msg}}</div>
<!-- /kami:template -->

<!-- kami:template login_unverified -->
<div class="kc-notice kc-notice-error" role="alert">{{msg}}</div>
<form action="UserAccount/resend_verification"
      method="post"
      data-ajax-form
      data-target="#login-result"
      style="margin-top:12px">
    <input type="hidden" name="identity" value="{{identity}}">
    <button class="kc-btn kc-btn-secondary kc-width-full" type="submit">
        {{phrase.resend_verification}}
    </button>
</form>
<!-- /kami:template -->

<!-- kami:template verification_resend_result -->
<div class="kc-notice kc-notice-success" role="status">{{msg}}</div>
<!-- /kami:template -->

<!-- kami:template login_result -->
<div class="kc-notice kc-notice-success" role="status">{{msg}}</div>
{{post_login_action}}
<!-- /kami:template -->

<!-- kami:template login_reload -->
<script>
    window.setTimeout(function () {
        window.location.reload();
    }, 2000);
</script>
<!-- /kami:template -->

<!-- kami:template login_redirect -->
<script>
    window.setTimeout(function () {
        window.location.assign({{redirect_url}});
    }, 2000);
</script>
<!-- /kami:template -->



<!-- kami:template register_result -->
<div class="kc-notice kc-notice-success" role="status">{{msg}}</div>
<!-- /kami:template -->



<!-- kami:template password_reset_request_result -->
<div class="kc-notice kc-notice-success" role="status">{{msg}}</div>
<!-- /kami:template -->

<!-- kami:template password_reset_email_html -->
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{phrase.password_reset_email_subject}}</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f5f7fb;padding:24px 0;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;background:#fff;border-radius:12px;">
        <tr><td style="padding:32px 32px 16px;font-size:24px;font-weight:bold;color:#111827;">{{phrase.password_reset_email_subject}}</td></tr>
        <tr><td style="padding:0 32px 16px;font-size:16px;line-height:1.6;color:#374151;">{{phrase.hello}}, {{username}}</td></tr>
        <tr><td style="padding:0 32px 24px;font-size:16px;line-height:1.6;color:#374151;">{{phrase.password_reset_email_intro}}</td></tr>
        <tr><td style="padding:0 32px 32px;"><a href="{{reset_url}}" style="display:inline-block;padding:14px 24px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;font-size:16px;font-weight:bold;">{{phrase.reset_password}}</a></td></tr>
        <tr><td style="padding:0 32px 12px;font-size:14px;line-height:1.6;color:#6b7280;">{{phrase.or_copy_link}}</td></tr>
        <tr><td style="padding:0 32px 24px;font-size:14px;line-height:1.6;word-break:break-all;"><a href="{{reset_url}}" style="color:#2563eb;text-decoration:underline;">{{reset_url}}</a></td></tr>
        <tr><td style="padding:0 32px 16px;font-size:14px;line-height:1.6;color:#6b7280;">{{phrase.password_reset_ignore}}</td></tr>
        <tr><td style="padding:0 32px 32px;font-size:14px;line-height:1.6;color:#6b7280;">Best regards,<br>{{site_name}}</td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
<!-- /kami:template -->

<!-- kami:template password_reset_email_txt -->
{{phrase.password_reset_email_subject}}

{{phrase.hello}}, {{username}}

{{phrase.password_reset_email_intro}}

{{reset_url}}

{{phrase.password_reset_ignore}}

Best regards,
{{site_name}}
<!-- /kami:template -->

<!-- kami:template password_reset_form_error -->
<div class="password-reset-message password-reset-message-error" role="alert">{{msg}}</div>
<!-- /kami:template -->

<!-- kami:template password_reset_form_success -->
<div class="password-reset-message password-reset-message-success" role="status" data-password-reset-success>{{msg}}</div>
<!-- /kami:template -->

<!-- kami:template password_reset_page -->
<!doctype html>
<html lang="{{language}}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{{phrase.reset_password}}</title>
  <style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f4f7fb;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#172033}.reset-card{width:min(520px,100%);padding:34px;border:1px solid #bfdbfe;border-radius:14px;background:#eff6ff;box-shadow:0 18px 50px rgba(15,23,42,.08)}.reset-card h1{margin:0 0 12px;font-size:26px;text-align:center}.reset-card>p{margin:0 0 24px;line-height:1.6;text-align:center}.reset-field{margin-bottom:16px}.reset-field label{display:block;margin-bottom:6px;font-weight:600}.reset-field input{width:100%;padding:11px 12px;border:1px solid #93c5fd;border-radius:8px;background:#fff;font:inherit}.reset-submit,.reset-home{display:block;width:100%;padding:11px 18px;border:0;border-radius:8px;font:inherit;font-weight:600;text-align:center}.reset-submit{background:#2563eb;color:#fff;cursor:pointer}.reset-submit:disabled{opacity:.6;cursor:default}.reset-home{margin-top:14px;background:#fff;color:#1d4ed8;text-decoration:none;border:1px solid #bfdbfe}.password-reset-result{margin-bottom:16px}.password-reset-message{padding:11px 12px;border-radius:8px;line-height:1.5}.password-reset-message-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}.password-reset-message-success{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}</style>
</head>
<body>
  <main class="reset-card">
    <h1>{{phrase.reset_password}}</h1>
    <p>{{phrase.password_reset_form_help}}</p>
    <div class="password-reset-result" data-password-reset-result aria-live="polite"></div>
    <form action="{{ajax_url}}" method="post" data-password-reset-form>
      <input type="hidden" name="token" value="{{token}}">
      <div class="reset-field">
        <label for="password-reset-new">{{phrase.new_password}}</label>
        <input id="password-reset-new" name="password" type="password" autocomplete="new-password" minlength="8" required autofocus>
      </div>
      <div class="reset-field">
        <label for="password-reset-repeat">{{phrase.repeat_password}}</label>
        <input id="password-reset-repeat" name="password_repeat" type="password" autocomplete="new-password" minlength="8" required>
      </div>
      <button class="reset-submit" type="submit">{{phrase.reset_password}}</button>
    </form>
    <a class="reset-home" href="{{home_url}}">{{phrase.return_to_site}}</a>
  </main>
  <script src="/assets/js/common.js"></script>
  <script>
  (function(){
    var form=document.querySelector('[data-password-reset-form]');
    var result=document.querySelector('[data-password-reset-result]');
    if(!form||!result)return;
    form.addEventListener('submit',async function(event){
      event.preventDefault();
      var button=form.querySelector('button[type="submit"]');
      if(button)button.disabled=true;
      try{
        var response=await fetch(form.action,{
          method:'POST',
          body:new FormData(form),
          credentials:'same-origin',
          headers:{'X-Requested-With':'XMLHttpRequest'}
        });
        if(!response.ok)throw new Error('HTTP '+response.status);
        result.innerHTML=await response.text();
        if(result.querySelector('[data-password-reset-success]'))form.hidden=true;
      }catch(e){
        result.textContent='{{phrase.server_unreachable}}';
      }finally{
        if(button)button.disabled=false;
      }
    });
  })();
  </script>
</body>
</html>
<!-- /kami:template -->

<!-- kami:template password_reset_error_page -->
<!doctype html>
<html lang="{{language}}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{{phrase.password_reset_error_title}}</title>
  <style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f7f7f8;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#2b1720}.reset-card{width:min(520px,100%);padding:34px;border:1px solid #fecaca;border-radius:14px;background:#fef2f2;box-shadow:0 18px 50px rgba(15,23,42,.08);text-align:center}.reset-card h1{margin:0 0 14px;font-size:26px}.reset-card p{margin:0 0 24px;line-height:1.6}.reset-home{display:inline-block;padding:11px 18px;border-radius:8px;background:#b91c1c;color:#fff;text-decoration:none;font-weight:600}</style>
</head>
<body>
  <main class="reset-card">
    <h1>{{phrase.password_reset_error_title}}</h1>
    <p>{{message}}</p>
    <a class="reset-home" href="{{home_url}}">{{phrase.return_to_site}}</a>
  </main>
</body>
</html>
<!-- /kami:template -->

<!-- kami:template verify_email_html -->
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{phrase.verify_your_email}}</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f5f7fb;padding:24px 0;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;background:#fff;border-radius:12px;">
        <tr><td style="padding:32px 32px 16px;font-size:24px;font-weight:bold;color:#111827;">{{phrase.verify_your_email}}</td></tr>
        <tr><td style="padding:0 32px 16px;font-size:16px;line-height:1.6;color:#374151;">{{phrase.hello}}, {{username}}</td></tr>
        <tr><td style="padding:0 32px 16px;font-size:16px;line-height:1.6;color:#374151;">{{phrase.thankyou}}</td></tr>
        <tr><td style="padding:0 32px 24px;font-size:16px;line-height:1.6;color:#374151;">{{phrase.please_click}}</td></tr>
        <tr><td style="padding:0 32px 32px;"><a href="{{verification_url}}" style="display:inline-block;padding:14px 24px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;font-size:16px;font-weight:bold;">{{phrase.verify_email_button}}</a></td></tr>
        <tr><td style="padding:0 32px 12px;font-size:14px;line-height:1.6;color:#6b7280;">{{phrase.or_copy_link}}</td></tr>
        <tr><td style="padding:0 32px 24px;font-size:14px;line-height:1.6;word-break:break-all;"><a href="{{verification_url}}" style="color:#2563eb;text-decoration:underline;">{{verification_url}}</a></td></tr>
        <tr><td style="padding:0 32px 16px;font-size:14px;line-height:1.6;color:#6b7280;">{{phrase.ignore}}</td></tr>
        <tr><td style="padding:0 32px 32px;font-size:14px;line-height:1.6;color:#6b7280;">Best regards,<br>{{site_name}}</td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
<!-- /kami:template -->

<!-- kami:template verify_email_txt -->
{{phrase.verify_your_email}}

{{phrase.hello}}, {{username}}

{{phrase.thankyou}}
{{phrase.please_click}}

{{verification_url}}

{{phrase.ignore}}

Best regards,
{{site_name}}
<!-- /kami:template -->

<!-- kami:template verification_resend_form -->
<div class="verify-resend">
  <p>{{phrase.verification_resend_prompt}}</p>
  <form action="{{ajax_url}}" method="post" data-verification-resend-form>
    <label for="verification-resend-email">{{phrase.email}}</label>
    <input id="verification-resend-email" name="identity" type="email" autocomplete="email" required>
    <button type="submit">{{phrase.resend_verification}}</button>
  </form>
  <div class="verify-resend-result" data-verification-resend-result aria-live="polite"></div>
  <div data-verification-resend-error hidden>{{phrase.server_unreachable}}</div>
</div>
<script>
(function(){
  var form=document.querySelector('[data-verification-resend-form]');
  if(!form)return;
  form.addEventListener('submit',async function(event){
    event.preventDefault();
    var button=form.querySelector('button[type="submit"]');
    var result=document.querySelector('[data-verification-resend-result]');
    var error=document.querySelector('[data-verification-resend-error]');
    if(button)button.disabled=true;
    try{
      var response=await fetch(form.action,{
        method:'POST',
        body:new FormData(form),
        credentials:'same-origin',
        headers:{'X-Requested-With':'XMLHttpRequest'}
      });
      if(!response.ok)throw new Error('HTTP '+response.status);
      result.innerHTML=await response.text();
      form.hidden=true;
    }catch(e){
      result.textContent=error?error.textContent:'';
    }finally{
      if(button)button.disabled=false;
    }
  });
})();
</script>
<!-- /kami:template -->

<!-- kami:template account_inactive_page -->
<!doctype html>
<html lang="{{language}}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{{phrase.account_inactive_title}}</title>
  <style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f7f7f8;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#2b1720}.account-card{width:min(520px,100%);padding:34px;border:1px solid #fed7aa;border-radius:14px;background:#fff7ed;box-shadow:0 18px 50px rgba(15,23,42,.08);text-align:center}.account-card h1{margin:0 0 14px;font-size:26px}.account-card p{margin:0 0 24px;line-height:1.6}.account-button{display:inline-block;padding:11px 18px;border-radius:8px;background:#9a3412;color:#fff;text-decoration:none;font-weight:600}</style>
</head>
<body>
  <main class="account-card">
    <h1>{{phrase.account_inactive_title}}</h1>
    <p>{{phrase.account_inactive_message}}</p>
    <a class="account-button" href="{{home_url}}">{{phrase.return_to_site}}</a>
  </main>
</body>
</html>
<!-- /kami:template -->

<!-- kami:template verification_success_page -->
<!doctype html>
<html lang="{{language}}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{{phrase.verification_success_title}}</title>
  <style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f4f7fb;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#172033}.verify-card{width:min(520px,100%);padding:34px;border:1px solid #bfdbfe;border-radius:14px;background:#eff6ff;box-shadow:0 18px 50px rgba(15,23,42,.08);text-align:center}.verify-card h1{margin:0 0 14px;font-size:26px}.verify-card p{margin:0 0 24px;line-height:1.6}.verify-button{display:inline-block;padding:11px 18px;border-radius:8px;background:#2563eb;color:#fff;text-decoration:none;font-weight:600}.verify-note{margin-top:18px!important;font-size:13px;color:#64748b}</style>
</head>
<body>
  <main class="verify-card">
    <h1>{{phrase.verification_success_title}}</h1>
    <p>{{message}}</p>
    <a class="verify-button" href="{{home_url}}">{{phrase.return_to_site}}</a>
    <p class="verify-note">{{phrase.redirecting_to_site}}</p>
  </main>
  <script>window.setTimeout(function(){window.location.assign("{{home_url}}");},3000);</script>
</body>
</html>
<!-- /kami:template -->

<!-- kami:template verification_error_page -->
<!doctype html>
<html lang="{{language}}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{{phrase.verification_error_title}}</title>
  <style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f7f7f8;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#2b1720}.verify-card{width:min(520px,100%);padding:34px;border:1px solid #fecaca;border-radius:14px;background:#fef2f2;box-shadow:0 18px 50px rgba(15,23,42,.08);text-align:center}.verify-card h1{margin:0 0 14px;font-size:26px}.verify-card p{margin:0 0 24px;line-height:1.6}.verify-button{display:inline-block;padding:11px 18px;border-radius:8px;background:#b91c1c;color:#fff;text-decoration:none;font-weight:600}.verify-resend{margin:0 0 24px;padding-top:20px;border-top:1px solid #fecaca;text-align:left}.verify-resend p{margin:0 0 12px}.verify-resend label{display:block;margin-bottom:6px;font-weight:600}.verify-resend input{width:100%;padding:10px 12px;border:1px solid #fca5a5;border-radius:8px;background:#fff;font:inherit}.verify-resend button{width:100%;margin-top:10px;padding:11px 18px;border:0;border-radius:8px;background:#b91c1c;color:#fff;font:inherit;font-weight:600;cursor:pointer}.verify-resend button:disabled{opacity:.6;cursor:default}.verify-resend-result{margin-top:12px}.verify-resend-result .kc-notice{padding:10px 12px;border-radius:8px;background:#fff7ed;color:#7c2d12}</style>
</head>
<body>
  <main class="verify-card">
    <h1>{{phrase.verification_error_title}}</h1>
    <p>{{message}}</p>
    {{resend_form}}
    <a class="verify-button" href="{{home_url}}">{{phrase.return_to_site}}</a>
  </main>
  {{common_js}}
</body>
</html>
<!-- /kami:template -->

<!-- kami:template change_email_html -->
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{phrase.email_change_subject}}</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f5f7fb;padding:24px 0;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;background:#fff;border-radius:12px;">
        <tr><td style="padding:32px 32px 16px;font-size:24px;font-weight:bold;color:#111827;">{{phrase.email_change_subject}}</td></tr>
        <tr><td style="padding:0 32px 16px;font-size:16px;line-height:1.6;color:#374151;">{{phrase.hello}}, {{username}}</td></tr>
        <tr><td style="padding:0 32px 16px;font-size:16px;line-height:1.6;color:#374151;">{{phrase.email_change_intro}}</td></tr>
        <tr><td style="padding:0 32px 24px;font-size:16px;line-height:1.6;color:#374151;">{{phrase.email_change_confirm}}</td></tr>
        <tr><td style="padding:0 32px 32px;"><a href="{{verification_url}}" style="display:inline-block;padding:14px 24px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;font-size:16px;font-weight:bold;">{{phrase.email_change_button}}</a></td></tr>
        <tr><td style="padding:0 32px 12px;font-size:14px;line-height:1.6;color:#6b7280;">{{phrase.or_copy_link}}</td></tr>
        <tr><td style="padding:0 32px 24px;font-size:14px;line-height:1.6;word-break:break-all;"><a href="{{verification_url}}" style="color:#2563eb;text-decoration:underline;">{{verification_url}}</a></td></tr>
        <tr><td style="padding:0 32px 16px;font-size:14px;line-height:1.6;color:#6b7280;">{{phrase.email_change_ignore}}</td></tr>
        <tr><td style="padding:0 32px 32px;font-size:14px;line-height:1.6;color:#6b7280;">Best regards,<br>{{site_name}}</td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
<!-- /kami:template -->

<!-- kami:template change_email_txt -->
{{phrase.email_change_subject}}

{{phrase.hello}}, {{username}}

{{phrase.email_change_intro}}
{{phrase.email_change_confirm}}

{{verification_url}}

{{phrase.email_change_ignore}}

Best regards,
{{site_name}}
<!-- /kami:template -->

<!-- kami:template email_changed_notice_html -->
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{phrase.email_changed_notice_subject}}</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f5f7fb;padding:24px 0;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;background:#fff;border-radius:12px;">
        <tr><td style="padding:32px 32px 16px;font-size:24px;font-weight:bold;color:#111827;">{{phrase.email_changed_notice_subject}}</td></tr>
        <tr><td style="padding:0 32px 16px;font-size:16px;line-height:1.6;color:#374151;">{{phrase.hello}}, {{username}}</td></tr>
        <tr><td style="padding:0 32px 16px;font-size:16px;line-height:1.6;color:#374151;">{{phrase.email_changed_notice}} <strong>{{new_email}}</strong></td></tr>
        <tr><td style="padding:0 32px 16px;font-size:14px;line-height:1.6;color:#991b1b;">{{phrase.email_changed_notice_security}}</td></tr>
        <tr><td style="padding:0 32px 32px;font-size:14px;line-height:1.6;color:#6b7280;">Best regards,<br>{{site_name}}</td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
<!-- /kami:template -->

<!-- kami:template email_changed_notice_txt -->
{{phrase.email_changed_notice_subject}}

{{phrase.hello}}, {{username}}

{{phrase.email_changed_notice}} {{new_email}}

{{phrase.email_changed_notice_security}}

Best regards,
{{site_name}}
<!-- /kami:template -->

<!-- kami:template email_change_success_page -->
<!doctype html>
<html lang="{{language}}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{{phrase.email_change_success_title}}</title>
  <style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f4f7fb;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#172033}.email-card{width:min(520px,100%);padding:34px;border:1px solid #bfdbfe;border-radius:14px;background:#eff6ff;box-shadow:0 18px 50px rgba(15,23,42,.08);text-align:center}.email-card h1{margin:0 0 14px;font-size:26px}.email-card p{margin:0 0 24px;line-height:1.6}.email-button{display:inline-block;padding:11px 18px;border-radius:8px;background:#2563eb;color:#fff;text-decoration:none;font-weight:600}</style>
</head>
<body>
  <main class="email-card">
    <h1>{{phrase.email_change_success_title}}</h1>
    <p>{{message}}</p>
    <a class="email-button" href="{{home_url}}">{{phrase.return_to_site}}</a>
  </main>
</body>
</html>
<!-- /kami:template -->

<!-- kami:template email_change_error_page -->
<!doctype html>
<html lang="{{language}}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{{phrase.email_change_error_title}}</title>
  <style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f7f7f8;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#2b1720}.email-card{width:min(520px,100%);padding:34px;border:1px solid #fecaca;border-radius:14px;background:#fef2f2;box-shadow:0 18px 50px rgba(15,23,42,.08);text-align:center}.email-card h1{margin:0 0 14px;font-size:26px}.email-card p{margin:0 0 24px;line-height:1.6}.email-button{display:inline-block;padding:11px 18px;border-radius:8px;background:#b91c1c;color:#fff;text-decoration:none;font-weight:600}</style>
</head>
<body>
  <main class="email-card">
    <h1>{{phrase.email_change_error_title}}</h1>
    <p>{{message}}</p>
    <a class="email-button" href="{{home_url}}">{{phrase.return_to_site}}</a>
  </main>
</body>
</html>
<!-- /kami:template -->
