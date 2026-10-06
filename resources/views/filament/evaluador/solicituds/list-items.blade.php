<div class="space-y-4">
    @if ($items->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">No hay {{ $type === 'adjunto' ? 'documentos' : ($type === 'regulador' ? 'reguladores' : 'cilindros') }} registrados.</p>
    @endif
    @foreach ($items as $index => $item)
        @php
            $url = \App\Support\Archivo::url($item->ruta_archivo ?? null);
            $extension = pathinfo($item->ruta_archivo ?? '', PATHINFO_EXTENSION);
            $isImage = in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'webp', 'gif']);
        @endphp
        
        <section class="glass-card fi-section border-gray-300 dark:border-white/10 shadow-sm hover:shadow-md transition-all overflow-hidden bg-white dark:bg-gray-950">
            
            {{-- HEADER --}}
            <header class="flex items-center justify-between py-3 px-4 border-b border-gray-300 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                <div class="flex items-center gap-x-3">
                    <span class="flex h-5 w-5 items-center justify-center rounded-md bg-gray-900 dark:bg-white/10 text-[9px] font-black text-white dark:text-gray-300 tracking-tighter">
                        {{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}
                    </span>
                    <h3 class="text-xs font-black uppercase tracking-[0.15em] !text-gray-950 dark:!text-gray-200">
                        {{ $type }} <span class="opacity-40 font-medium">|</span> <span class="text-primary-600 dark:text-primary-400 font-bold">ID: {{ $item->id }}</span>
                    </h3>
                </div>
                
                @if($type === 'cilindro')
                    <span class="text-[9px] font-black px-2 py-0.5 rounded-full border border-danger-600/20 bg-danger-50 dark:bg-danger-500/10 !text-danger-600 dark:!text-danger-400">
                        IVS CONTROL
                    </span>
                @endif
            </header>

            <div class="p-5">
                {{-- Layout Condicional para Adjuntos con Vista Previa --}}
                @if($type === 'adjunto')
                    <div class="flex flex-col md:flex-row gap-6">
                        {{-- ÁREA DE VISTA PREVIA --}}
                        <a @if($url) href="{{ $url }}" target="_blank" rel="noopener" @endif class="block w-full md:w-48 h-32 rounded-lg border-2 border-dashed border-gray-300 dark:border-white/10 overflow-hidden bg-gray-100 dark:bg-white/5 hover:border-primary-500 transition group relative">
                            @if($isImage)
                                <img src="{{ $url }}" alt="Preview" class="w-full h-full object-cover group-hover:scale-105 transition">
                            @else
                                <div class="flex flex-col items-center justify-center h-full text-gray-400">
                                    <x-heroicon-o-document-text class="w-10 h-10" />
                                    <span class="text-[9px] font-bold uppercase">{{ $extension }}</span>
                                </div>
                            @endif
                            <div class="absolute inset-0 bg-primary-600/20 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white text-[10px] font-black tracking-widest uppercase">
                                Ver Archivo
                            </div>
                        </a>

                        {{-- DATOS DEL ADJUNTO --}}
                        <div class="flex-1 flex flex-col justify-center">
                            <dt class="text-[9px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1">Nombre del Documento</dt>
                            <dd class="text-base font-bold !text-gray-950 dark:!text-white mb-4">{{ \App\Filament\Resources\Solicituds\Schemas\AttachmentForm::etiqueta($item->nombre_adjunto) }}</dd>
                            
                            <div class="flex gap-4">
                                <div class="space-y-1">
                                    <dt class="text-[9px] font-black text-gray-400 uppercase">Formato</dt>
                                    <dd class="text-xs font-mono font-bold uppercase !text-gray-600 dark:!text-gray-400 tracking-tighter">.{{ $extension }}</dd>
                                </div>
                                <div class="space-y-1 border-l border-gray-200 dark:border-white/10 pl-4">
                                    <dt class="text-[9px] font-black text-gray-400 uppercase">Estado</dt>
                                    @if ($url)
                                        <dd class="text-xs font-bold text-success-600 dark:text-success-400 italic leading-none">Cargado</dd>
                                    @else
                                        <dd class="text-xs font-bold text-danger-600 dark:text-danger-400 italic leading-none">Sin archivo</dd>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                @else
                    {{-- GRID PARA REGULADOR Y CILINDRO (Igual que antes pero con !text-gray-950) --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6">
                        @if($type === 'regulador')
                            <div class="fi-in-entry-wrp">
                                <dt class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1">Fabricante</dt>
                                <dd class="text-sm font-bold !text-gray-950 dark:!text-white">{{ $item->brand?->nombre ?? '—' }}</dd>
                            </div>
                            <div class="fi-in-entry-wrp">
                                <dt class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1">N° Serie</dt>
                                <dd class="font-mono text-sm font-black text-danger-600 dark:text-danger-400">{{ $item->numero_serie }}</dd>
                            </div>
                        @else {{-- CILINDROS --}}
                            <div class="space-y-1">
                                <dt class="text-[9px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">Marca</dt>
                                <dd class="text-sm font-bold !text-gray-950 dark:!text-white">{{ $item->brand?->nombre ?? '—' }}</dd>
                            </div>
                            <div class="space-y-1">
                                <dt class="text-[9px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">Serie</dt>
                                <dd class="font-mono text-sm font-black text-primary-600 dark:text-primary-400">{{ $item->numero_serie }}</dd>
                            </div>
                            <div class="space-y-1 text-center border-x border-gray-200 dark:border-white/10 px-4">
                                <dt class="text-[9px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">Capacidad</dt>
                                <dd class="text-sm font-black !text-gray-950 dark:!text-white">{{ $item->capacidad }} <span class="text-[10px] opacity-40">LTS</span></dd>
                            </div>
                            <div class="space-y-1 pl-4">
                                <dt class="text-[9px] font-black text-danger-600 dark:text-danger-400 uppercase tracking-tighter italic">Vence PH</dt>
                                <dd class="text-sm font-black text-danger-600 dark:text-danger-400">
                                    {{ $item->fecha_prueba ? \Carbon\Carbon::parse($item->fecha_prueba)->format('d/m/Y') : '—' }}
                                </dd>
                            </div>
                            <div class="space-y-1 text-right">
                                <dt class="text-[9px] font-bold text-gray-400 uppercase">Fab.</dt>
                                <dd class="text-xs font-bold !text-gray-600 dark:!text-gray-400">{{ $item->fecha_fabricacion ? \Carbon\Carbon::parse($item->fecha_fabricacion)->format('m/Y') : '—' }}</dd>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </section>
    @endforeach
</div>