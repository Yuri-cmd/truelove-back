<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $nombre }} en TrueLove</title>
    <meta name="description" content="Pide en {{ $nombre }} con TrueLove. {{ $direccion }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="TrueLove">
    <meta property="og:title" content="{{ $nombre }} en TrueLove">
    <meta property="og:description" content="{{ $tipo ? $tipo . ' · ' : '' }}{{ $direccion }}. Pídelo por TrueLove.">
    <meta property="og:url" content="{{ $enlace }}">
    @if($imagen)
    <meta property="og:image" content="{{ $imagen }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ $imagen }}">
    @endif
    <style>
        :root { --rojo: #EF2A39; --negro: #111; --gris: #6C6C73; --fondo: #F5F5F6; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: var(--fondo); color: var(--negro); min-height: 100vh; display: grid; place-items: center; padding: 16px; }
        .tarjeta { width: 100%; max-width: 420px; background: #fff; border-radius: 28px; overflow: hidden; box-shadow: 0 24px 48px -24px rgba(0,0,0,.25); }
        .portada { height: 190px; background: var(--rojo) center / cover no-repeat; }
        .cuerpo { padding: 20px 20px 24px; }
        .cuerpo h1 { margin: 0 0 4px; font-size: 26px; letter-spacing: -0.03em; line-height: 1.1; }
        .cuerpo p { margin: 0 0 20px; color: var(--gris); font-size: 14px; }
        .boton { display: block; text-align: center; text-decoration: none; padding: 15px 18px; border-radius: 999px; font-weight: 700; font-size: 16px; }
        .boton.principal { background: var(--rojo); color: #fff; }
        .tiendas { display: flex; gap: 8px; margin-top: 10px; }
        .tiendas a { flex: 1; background: var(--fondo); color: var(--negro); font-size: 14px; padding: 12px; }
        .nota { margin-top: 14px; font-size: 12px; color: var(--gris); text-align: center; }
    </style>
</head>
<body>
    <main class="tarjeta">
        <div class="portada" @if($imagen) style="background-image: url('{{ $imagen }}')" @endif></div>
        <div class="cuerpo">
            <h1>{{ $nombre }}</h1>
            <p>{{ $tipo ? $tipo . ' · ' : '' }}{{ $direccion }}</p>
            <a class="boton principal" id="abrir" href="truelove://restaurante/{{ $idLocal }}">Abrir en la app</a>
            @if($playStore || $appStore)
            <div class="tiendas">
                @if($playStore)<a class="boton" href="{{ $playStore }}">Google Play</a>@endif
                @if($appStore)<a class="boton" href="{{ $appStore }}">App Store</a>@endif
            </div>
            <div class="nota">¿No tienes TrueLove? Descárgala y vuelve a tocar este enlace.</div>
            @endif
        </div>
    </main>
</body>
</html>
