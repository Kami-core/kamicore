<!-- kami:template error-403 -->
<html lang="{{language}}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{status}} — {{title}}</title>
    {{system_css}}
    {{assets_css}}
    <link rel="stylesheet" href="/themes/default/assets/css/default.css">
    {{system_js}}
</head>
<body class="kami kami-frontend">

{{template:bodyheader}}

    <main class="kc-error-page">
        <div class="kc-container kc-error-card kc-error-403">
            <h1>{{title}}</h1>
            <p>{{message}}</p>
        </div>
    </main>
</body>
</html>
<!-- /kami:template -->

<!-- kami:template error-404 -->
<!DOCTYPE html>
<html lang="{{language}}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{status}} — {{title}}</title>
    {{system_css}}
    {{assets_css}}
    <link rel="stylesheet" href="/themes/default/assets/css/default.css">
    {{system_js}}
</head>
<body class="kami kami-frontend">

{{template:bodyheader}}

    <main class="kc-error-page">
        <div class="kc-container kc-error-card kc-error-404">
            <h1>{{title}}</h1>
            <p>{{message}}</p>
        </div>
    </main>
</body>
</html>
<!-- /kami:template -->

<!-- kami:template error -->
<!DOCTYPE html>
<html lang="{{language}}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{status}} — {{title}}</title>
    <link rel="stylesheet" href="/themes/default/assets/css/default.css">
</head>
<body class="kami-frontend">
    <main class="kc-error-page">
        <div class="kc-error-card">
            <div class="kc-error-status">{{status}}</div>
            <h1>{{title}}</h1>
            <p>{{message}}</p>
            <a class="kc-btn kc-button" href="{{home_url}}">Home</a>
        </div>
    </main>
</body>
</html>
<!-- /kami:template -->
