{{-- Ilustración isométrica de la pantalla de ingreso: vehículo, plataforma de inspección y certificado aprobado. --}}
<svg viewBox="0 0 500 440" xmlns="http://www.w3.org/2000/svg" role="img">
    {{-- Sombra del piso --}}
    <ellipse cx="290" cy="380" rx="200" ry="50" fill="#efdedb" opacity=".55" />

    {{-- Piezas sueltas --}}
    <g>
        <polygon points="20,190 34,183 48,190 34,197" fill="#c98b8d" />
        <polygon points="230,120 242,114 254,120 242,126" fill="#e8c8a0" />
        <polygon points="470,250 482,244 494,250 482,256" fill="#e8c8a0" />
        <polygon points="200,180 210,175 220,180 210,185" fill="#e9cfcc" />
        <polygon points="460,370 470,365 480,370 470,375" fill="#c98b8d" />
        <polygon points="150,420 162,414 174,420 162,426" fill="#c98b8d" />
        <polygon points="110,385 120,380 130,385 120,390" fill="#e8c8a0" />
        <polygon points="300,432 310,427 320,432 310,437" fill="#e9cfcc" />
        <polygon points="40,310 52,304 64,310 52,316" fill="#e9cfcc" />
    </g>

    {{-- Vehículo --}}
    <g>
        <ellipse cx="102" cy="268" rx="82" ry="34" fill="#e7d2cf" opacity=".7" />
        {{-- carrocería --}}
        <polygon points="30,218 125.3,273 172.9,245.5 77.6,190.5" fill="#e4b3b2" />
        <polygon points="30,250 125.3,305 125.3,273 30,218" fill="#b9787b" />
        <polygon points="125.3,305 172.9,277.5 172.9,245.5 125.3,273" fill="#94595e" />
        {{-- cabina --}}
        <polygon points="58.6,205 101.5,229.8 139.5,207.8 96.6,183" fill="#f2dedb" />
        <polygon points="58.6,229 101.5,253.8 101.5,229.8 58.6,205" fill="#f7ebe9" />
        <polygon points="101.5,253.8 139.5,231.8 139.5,207.8 101.5,229.8" fill="#dcbcbb" />
        <line x1="80" y1="241.4" x2="80" y2="217.4" stroke="#b9787b" stroke-width="2.5" />
        {{-- farolas --}}
        <polygon points="131,293 141,287 141,281 131,287" fill="#e8c8a0" />
        <polygon points="160,276 168,271 168,265 160,270" fill="#e8c8a0" />
        {{-- llantas --}}
        <g fill="#3f3236">
            <circle r="12" transform="matrix(.866 .5 0 1 52 254)" />
            <circle r="12" transform="matrix(.866 .5 0 1 104 284)" />
        </g>
        <g fill="#d9c3c1">
            <circle r="5" transform="matrix(.866 .5 0 1 52 254)" />
            <circle r="5" transform="matrix(.866 .5 0 1 104 284)" />
        </g>
    </g>

    {{-- Certificado de pie, detrás del portátil --}}
    <g>
        <polygon points="450,120 462,114 462,314 450,320" fill="#c9a2a2" />
        <g transform="matrix(.894 .447 0 1 300 45)">
            <rect width="168" height="200" fill="#fbf3f1" />
            <rect width="168" height="34" fill="#a9575c" />
            <rect x="16" y="12" width="70" height="5" rx="2.5" fill="#f6dcdc" />
            <rect x="16" y="21" width="44" height="4" rx="2" fill="#d79a9d" />
            {{-- sello --}}
            <circle cx="36" cy="66" r="16" fill="#e8c8a0" />
            <path d="M28 66l6 6 11-12" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M28 80l-5 14 8-4 5 6 4-14z M44 80l5 14-8-4-5 6-4-14z" fill="#c98b8d" />
            <rect x="64" y="56" width="84" height="5" rx="2.5" fill="#dcc4c2" />
            <rect x="64" y="67" width="64" height="5" rx="2.5" fill="#e9d8d6" />
            <rect x="64" y="78" width="74" height="5" rx="2.5" fill="#e9d8d6" />
            {{-- lista de chequeo --}}
            <g>
                <rect x="16" y="112" width="12" height="12" rx="2" fill="#fff" stroke="#c98b8d" stroke-width="1.5" />
                <path d="M19 118l2.5 2.5 5-5" fill="none" stroke="#a9575c" stroke-width="2" stroke-linecap="round" />
                <rect x="36" y="116" width="100" height="4" rx="2" fill="#e9d8d6" />
                <rect x="16" y="134" width="12" height="12" rx="2" fill="#fff" stroke="#c98b8d" stroke-width="1.5" />
                <path d="M19 140l2.5 2.5 5-5" fill="none" stroke="#a9575c" stroke-width="2" stroke-linecap="round" />
                <rect x="36" y="138" width="86" height="4" rx="2" fill="#e9d8d6" />
                <rect x="16" y="156" width="12" height="12" rx="2" fill="#fff" stroke="#c98b8d" stroke-width="1.5" />
                <path d="M19 162l2.5 2.5 5-5" fill="none" stroke="#a9575c" stroke-width="2" stroke-linecap="round" />
                <rect x="36" y="160" width="94" height="4" rx="2" fill="#e9d8d6" />
                <rect x="16" y="178" width="12" height="12" rx="2" fill="#fff" stroke="#dcc4c2" stroke-width="1.5" />
                <rect x="36" y="182" width="70" height="4" rx="2" fill="#efe3e1" />
            </g>
        </g>
    </g>

    {{-- Burbuja de aviso sobre el certificado --}}
    <g>
        <circle cx="300" cy="30" r="17" fill="#e8c8a0" />
        <path d="M292 30l5.5 5.5 10-11" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" />
        <polygon points="322,40 352,55 352,80 322,65" fill="#f3e3e1" />
        <circle cx="331" cy="56" r="2" fill="#b9787b" />
        <circle cx="337" cy="59" r="2" fill="#b9787b" />
        <circle cx="343" cy="62" r="2" fill="#b9787b" />
    </g>

    {{-- Plataforma de inspección --}}
    <g>
        <polygon points="130,330 280,405 280,418 130,343" fill="#c99a9b" />
        <polygon points="280,405 450,320 450,333 280,418" fill="#9e6a6d" />
        <polygon points="130,330 300,245 450,320 280,405" fill="#f6e9e7" />
        {{-- carril de inspección --}}
        <g transform="matrix(170 -85 150 75 130 330)">
            <rect x="0.1" y="0.28" width="0.8" height="0.44" fill="#ead7d5" />
            <rect x="0.1" y="0.28" width="0.8" height="0.025" fill="#d9b9b8" />
            <rect x="0.1" y="0.695" width="0.8" height="0.025" fill="#d9b9b8" />
            <g fill="#fff">
                <rect x="0.14" y="0.488" width="0.09" height="0.024" />
                <rect x="0.3" y="0.488" width="0.09" height="0.024" />
                <rect x="0.46" y="0.488" width="0.09" height="0.024" />
                <rect x="0.62" y="0.488" width="0.09" height="0.024" />
                <rect x="0.78" y="0.488" width="0.09" height="0.024" />
            </g>
        </g>
    </g>

    {{-- Burbuja de mensaje --}}
    <g>
        <polygon points="30,345 64,362 64,396 30,379" fill="#a9575c" />
        <polygon points="64,362 70,359 70,393 64,396" fill="#834246" />
        <circle cx="40" cy="365" r="2.6" fill="#fff" />
        <circle cx="47" cy="369" r="2.6" fill="#fff" />
        <circle cx="54" cy="373" r="2.6" fill="#fff" />
    </g>
</svg>
