{{-- Pantalla de ingreso. Estilos en resources/css/filament/admin/theme.css (bloque «Pantalla de ingreso»). --}}
<div class="ivs-login">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=poppins:400,500,600&display=swap">

    {{-- Adornos del fondo --}}
    <div class="ivs-login__fondo" aria-hidden="true">
        <span class="ivs-login__curva"></span>
        <span class="ivs-login__circulo ivs-login__circulo--grande"></span>
        <span class="ivs-login__circulo ivs-login__circulo--chico"></span>
        <span class="ivs-login__punto ivs-login__punto--a"></span>
        <span class="ivs-login__punto ivs-login__punto--b"></span>
        <span class="ivs-login__triangulo ivs-login__triangulo--a"></span>
        <span class="ivs-login__triangulo ivs-login__triangulo--b"></span>
        <span class="ivs-login__cinta ivs-login__cinta--a"></span>
        <span class="ivs-login__cinta ivs-login__cinta--b"></span>
        <span class="ivs-login__marco"></span>
        <span class="ivs-login__puntos ivs-login__puntos--arriba"></span>
        <span class="ivs-login__puntos ivs-login__puntos--izq"></span>
        <span class="ivs-login__puntos ivs-login__puntos--abajo"></span>
    </div>

    <div class="ivs-login__tarjeta">
        <header class="ivs-login__cabecera">
            <span class="ivs-login__marca">
                <img src="{{ asset('images/logo-sm.png') }}" alt="" class="ivs-login__logo">
                <span><b>IVS</b> Certificaciones</span>
            </span>
        </header>

        <div class="ivs-login__cuerpo">
            <section class="ivs-login__form">
                <h1 class="ivs-login__titulo">{{ $this->getHeading() }}</h1>

                @if (filled($subheading = $this->getSubheading()))
                    <p class="ivs-login__subtitulo">{{ $subheading }}</p>
                @endif

                {{ $this->content }}
            </section>

            <div class="ivs-login__ilustracion" aria-hidden="true">
                @include('filament.pages.auth.login-ilustracion')
            </div>
        </div>

        <footer class="ivs-login__pie">
            <span class="ivs-login__copy">© IVS Certificaciones {{ now()->year }}</span>
            <span class="ivs-login__indicador" aria-hidden="true"><i></i><i></i><i></i></span>
        </footer>
    </div>

    <x-filament-actions::modals />
</div>
