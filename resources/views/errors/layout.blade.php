<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · Chorale Management</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;background:linear-gradient(145deg,#020617,#172033);color:#e2e8f0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,sans-serif}.shell{min-height:100vh;display:grid;place-items:center;padding:24px}.card{position:relative;width:min(100%,680px);overflow:hidden;border:1px solid #334155;border-radius:28px;background:#0f172aeF;padding:clamp(28px,7vw,56px);box-shadow:0 30px 80px #02061799}.glow{position:absolute;width:260px;height:260px;right:-100px;top:-120px;border-radius:999px;background:#f59e0b22;filter:blur(20px)}.code{margin:0;color:#fbbf24;font-size:.78rem;font-weight:800;letter-spacing:.18em;text-transform:uppercase}.icon{display:grid;place-items:center;width:64px;height:64px;margin:24px 0;border-radius:18px;background:#f59e0b1c;color:#fbbf24;font-size:30px}h1{margin:0;color:#fff;font-size:clamp(1.8rem,6vw,3rem);line-height:1.08}p{max-width:540px;margin:18px 0 0;color:#cbd5e1;font-size:1rem;line-height:1.7}.actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:30px}.button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;border-radius:12px;padding:10px 18px;background:#f59e0b;color:#172033;text-decoration:none;font-weight:800}.button.secondary{border:1px solid #475569;background:#1e293b;color:#f8fafc}.help{margin-top:28px;padding-top:20px;border-top:1px solid #334155;color:#94a3b8;font-size:.83rem}
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
