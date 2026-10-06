<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Restringido | IVS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 flex items-center justify-center min-h-screen p-4">

    <div class="w-full max-w-md bg-white rounded-3xl shadow-2xl overflow-hidden transform transition-all">
        
        <div class="h-2 bg-gradient-to-r from-red-500 to-orange-400"></div>

        <div class="p-8 md:p-12 text-center">
            <div class="mb-8 flex justify-center">
                <img src="{{ asset('images/logo-sm.png') }}" alt="Logo IVS" class="h-16 w-auto object-contain drop-shadow-sm">
            </div>

            <div class="inline-flex items-center justify-center w-20 h-20 bg-red-50 rounded-full mb-6">
                <svg class="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>

            <h1 class="text-3xl font-extrabold text-slate-900 mb-3 tracking-tight">
                Acceso Restringido
            </h1>
            
            <p class="text-slate-500 leading-relaxed mb-10">
                Por políticas de seguridad, Esta accion por el momento <span class="font-semibold text-slate-800">No está disponible, </span>.
            </p>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-xl text-white bg-slate-900 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-900 transition-all duration-200 shadow-lg hover:shadow-xl">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-slate-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </span>
                    Cerrar sesión segura
                </button>
            </form>
        </div>

        <div class="bg-slate-50 py-4 px-8 border-t border-slate-100">
            <p class="text-xs text-slate-400">
                &copy; {{ date('Y') }} IVS Compañia de certificaciones - Sistema de Seguridad Integral
            </p>
        </div>
    </div>

</body>
</html>