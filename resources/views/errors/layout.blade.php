<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · Chorale Management</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;background:#faf9f6;color:#302c32;font-family:"Segoe UI",system-ui,sans-serif}.shell{min-height:100vh;display:grid;place-items:center;padding:24px}.card{width:min(100%,680px);padding:clamp(24px,6vw,48px);border-top:3px solid #9a743e;background:#fff}.glow,.icon{display:none}.code{margin:0;color:#625b56;font-size:.9rem;font-weight:600}h1{margin:24px 0 0;color:#493447;font-family:Georgia,serif;font-size:clamp(2rem,6vw,3rem);font-weight:400;line-height:1.12}p{max-width:65ch;margin:18px 0 0;color:#625b56;font-size:1rem;line-height:1.7}.actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:30px}.button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;border:1px solid #493447;border-radius:6px;padding:10px 18px;background:#493447;color:#fff;text-decoration:none;font-weight:600}.button.secondary{border-color:#c8c2bd;background:#fff;color:#493447}.button:focus-visible{outline:3px solid #6b4964;outline-offset:4px}.help{margin-top:28px;padding-top:20px;border-top:1px solid #dedad6;font-size:.85rem}
    </style>
</head>
<body>
<main class="shell">
    <section class="card" role="alert" aria-labelledby="error-title">
        <span class="glow" aria-hidden="true"></span>
        <p class="code">Erreur {{ $status }}</p>
        <div class="icon" aria-hidden="true">{{ $icon ?? '♪' }}</div>
        <h1 id="error-title">{{ $title }}</h1>
        <p>{{ $message }}</p>
        <div class="actions">
            <a class="button" href="/">Revenir à l’accueil</a>
            <button class="button secondary" type="button" onclick="history.length > 1 ? history.back() : location.assign('/')">Page précédente</button>
        </div>
        <p class="help">Si le problème persiste, notez le code ci-dessus et transmettez-le au responsable de votre chorale.</p>
    </section>
</main>
</body>
</html>
