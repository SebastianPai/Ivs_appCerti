<?php

namespace App\Support;

use App\Models\Solicitud;

/**
 * Única fuente de verdad del checklist técnico (NTC 4821 / NTC 5212-1 / Res. 0957).
 * La usan el evaluador (para diligenciar) y el revisor (para auditar), así nunca
 * se desincronizan las preguntas entre ambos.
 *
 * Son los 61 ítems y 9 secciones de la plataforma anterior, con el texto literal de la norma.
 *
 * Cada ítem: id (clave en datos_checklist), numeral, texto, medicion (pide valor en cm),
 * minimo / maximo (cm) e inicial (respuesta sugerida cuando el prellenado está activo:
 * 'c', 'na', null = sin sugerencia, o el nombre de una regla de valoresIniciales()).
 *
 * Además hay respuestas que se fijan solas mientras el evaluador diligencia: ver reglasDinamicas().
 */
class ChecklistTecnico
{
    public static function secciones(): array
    {
        return [
            'documentacion' => [
                'titulo' => 'Anexos',
                'descripcion' => 'Soporte de anexos informativos',
                'icono' => 'heroicon-o-document-magnifying-glass',
                'columna' => 'Normativa / Documento',
                'items' => [
                    self::item('doc_0957', 'Resolución 0957 Anexo 1', 'Formato de preconversión y postconversión diligenciados', ['inicial' => 'anexo_prepos']),
                    self::item('doc_acta', 'Reglamento interno', 'Acta de monte y desmonte de cilindros (según aplique)', ['inicial' => 'anexo_acta']),
                    self::item('doc_ensayo', 'Reglamento interno', 'Registro de ensayo de cilindros (según aplique)', ['inicial' => 'anexo_ensayo']),
                ],
            ],
            'tuberias' => [
                'titulo' => 'Tuberías',
                'descripcion' => 'Tuberías, mangueras y accesorios',
                'icono' => 'heroicon-o-wrench-screwdriver',
                'columna' => 'Numeral NTC / Requisito',
                'items' => [
                    self::item('code_4_5', 'NTC 5212-1 Numeral 4.5', 'Excepto los cilindros y sus accesorios, que deben cumplir lo establecido en el numeral 4.1.2.2, los componentes se deben instalar al menos a 10 cm del sistema de escape; de lo contrario, se deben instalar pantallas de protección contra el calor.', ['medicion' => true, 'minimo' => 10]),
                    self::item('code_5_2_7', 'NTC 4821 Numeral 5.2.7', 'Las líneas de suministro deben estar instaladas a una distancia mínima de 20 cm de los terminales de la batería, a menos que se prevenga el contacto eléctrico.', ['medicion' => true, 'minimo' => 20]),
                    self::item('ntc_5_2_4', 'NTC 4821 Numeral 5.2.4', 'Las líneas rígidas y flexibles deben estar protegidas contra daños y fracturas debidas a esfuerzos o desgaste.'),
                    self::item('ntc_5_2_5', 'NTC 4821 Numeral 5.2.5', 'Las líneas rígidas no se pueden colocar en la parte inferior del chasis, deben ir, en lo posible, protegidas por el mismo.'),
                    self::item('ntc_5_2_6', 'NTC 4821 Numeral 5.2.6', 'Las líneas de suministro de combustible deben seguir la ruta más corta y práctica compatible con su flexibilidad y formabilidad y deben estar protegidas contra daños o roturas debido a choques, esfuerzos o desgaste por rozamiento. No deben estar dentro del compartimiento de pasajeros, a excepción del tramo de conexión al cilindro.'),
                    self::item('ntc_5_2_8', 'NTC 4821 Numeral 5.2.8', 'El proceso de instalación de las líneas rígidas no debe debilitar la estructura de ninguna parte del vehículo.'),
                    self::item('ntc_5_2_9', 'NTC 4821 Numeral 5.2.9', 'Las conexiones de las líneas rígidas no deben estar localizadas donde las eventuales fugas de GNCV puedan acumularse.'),
                    self::item('code_4_1_2_5', 'NTC 5212-1 Numeral 4.1.2.5', 'La tubería se debe instalar, si es posible, en el chasis, de manera que no ocurra daño por las vibraciones intrínsecas (por ejemplo: resonancia con la vibración del motor) y no haya puntos de fricción. Los intervalos entre dos puntos de fijación no deben ser superiores a 1 m, y la instalación de las tuberías y los accesorios debe estar de acuerdo con las especificaciones del fabricante de los tubos y accesorios.', ['medicion' => true]),
                    self::item('ntc_5_2_10', 'NTC 4821 Numeral 5.2.10', 'La línea rígida no debe estar localizada en el túnel por donde va el eje de la transmisión del vehículo.'),
                    self::item('code_5_2_11', 'NTC 4821 Numeral 5.2.11', 'La línea rígida y flexible de combustible que esté torcida, perforada o con cualquier otro daño debe ser reemplazada y no se admite ningún tipo de reparación sobre la misma.', ['inicial' => 'na']),
                    self::item('ntc_5_2_12', 'NTC 4821 Numeral 5.2.12', 'Los accesorios y conexiones deben estar localizados en lugares accesibles para permitir la inspección y mantenimiento.'),
                    self::item('ntc_5_2_13', 'NTC 4821 Numeral 5.2.13', 'Las líneas rígidas que atraviesan secciones de metal se deben proteger con un aislamiento que las proteja de desgaste por fricción. Este elemento puede ser una manguera de plástico o caucho.'),
                    self::item('ntc_5_2_15', 'NTC 4821 Numeral 5.2.15', 'Todas las líneas rígidas entre los cilindros y el punto de llenado deben disponerse de manera que permitan un ligero movimiento estructural para absorber las vibraciones, y que ante un impacto se evite su estrangulamiento o rotura.'),
                    self::item('ntc_5_2_16', 'NTC 4821 Numeral 5.2.16', 'No está permitido realizar: a) conexión de accesorios tipo "bushing" (manguito); b) uniones con niples que contienen rosca derecha e izquierda en la misma pieza; c) empalmes utilizando niples cerrados o muy próximos unos de otros.'),
                ],
            ],
            'regulador' => [
                'titulo' => 'Regulador',
                'descripcion' => 'Instalación del regulador de presión',
                'icono' => 'heroicon-o-cog',
                'columna' => 'Numeral NTC / Ubicación',
                'items' => [
                    self::item('ntc_5_3_1', 'NTC 4821 Numeral 5.3.1', 'El regulador o reguladores no se deben fijar directamente al motor del vehículo en el que van a ser instalados.'),
                    self::item('ntc_5_3_2', 'NTC 4821 Numeral 5.3.2', 'El regulador de presión debe estar: a) instalado de forma segura y en la parte más alta posible; b) protegido contra daños, choques, calor excesivo y equipos eléctricos; c) instalado en un lugar accesible.'),
                    self::item('ntc_5_3_5', 'NTC 4821 Numeral 5.3.5', 'El regulador de presión puede ser de dos tipos: de presión positiva o presión negativa; y puede ser de uno, dos o tres cuerpos.'),
                    self::item('ntc_5_3_6', 'NTC 4821 Numeral 5.3.6', 'El regulador de presión debe disponer de un sistema de fijación propio de modo que su peso no sea soportado por las líneas rígidas o flexibles adyacentes.'),
                    self::item('ntc_5_3_7', 'NTC 4821 Numeral 5.3.7', 'El regulador debe colocarse de manera que el desplazamiento y el movimiento del vehículo no afecte el funcionamiento del mismo.'),
                    self::item('ntc_5_3_10', 'NTC 4821 Numeral 5.3.10', 'El regulador que entregue la última etapa de presión debe ser instalado cerca al mezclador, para tener las mangueras lo más cortas posible, y evitar así estrangulaciones en las mismas.', ['inicial' => 'mezclador']),
                ],
            ],
            'valvulas' => [
                'titulo' => 'Válvulas',
                'descripcion' => 'Válvula de llenado y receptáculo',
                'icono' => 'heroicon-o-adjustments-vertical',
                'columna' => 'Numeral NTC / Componente',
                'items' => [
                    self::item('ntc_4_2_2_motor', 'NTC 5212-1 Numeral 4.2.2', 'Los receptáculos instalados dentro del compartimiento del motor deben ir unidos al chasis o cuerpo del vehículo. Se deben instalar a una distancia suficiente de la batería y el circuito de alta tensión, para evitar la posibilidad de ignición accidental.'),
                    self::item('ntc_5_4_1', 'NTC 4821 Numeral 5.4.1', 'La válvula de llenado (receptáculo) debe instalarse en un lugar seguro contra impactos, en la zona del motor u otra zona considerada segura.'),
                    self::item('ntc_5_4_2', 'NTC 4821 Numeral 5.4.2', 'La válvula de llenado debe estar equipada con un mecanismo de cheque que impida el flujo de GNC hacia el surtidor. La válvula de llenado debe cumplir con lo establecido en la NTC 4824.'),
                    self::item('ntc_5_4_3', 'NTC 4821 Numeral 5.4.3', 'Se debe instalar una válvula manual de cierre para aislar el conjunto de cilindros con el resto del equipo, para permitir realizar trabajos con las líneas completamente despresurizadas y controlar fugas en la válvula de llenado.', ['inicial' => 'varios_cilindros']),
                    self::item('ntc_5_4_4', 'NTC 4821 Numeral 5.4.4', 'Cuando el vehículo posee sistema bicombustible, debe tener elementos que aíslen el suministro de cada combustible al motor. Estos elementos deben ser electroválvulas de corte para sistema de alimentación de combustible a carburador que cuenten con la opción de operación manual las cuales deben ser instaladas cerca al punto donde se produce el suministro de cada combustible al motor y de manera que quede firmemente acoplada.', ['inicial' => 'carburado']),
                    self::item('ntc_5_4_5', 'NTC 4821 Numeral 5.4.5', 'Cuando el vehículo trabaja con sistema de alimentación de combustible a carburador, la electroválvula de corte de gasolina debe ser instalada entre la bomba de gasolina y el carburador mediante líneas rígidas, líneas flexibles y accesorios equivalentes a aquellos que utiliza el fabricante del vehículo a la salida de la bomba.', ['inicial' => 'carburado']),
                    self::item('ntc_4_1_2_1', 'NTC 5212-1 Numeral 4.1.2.1', 'El receptáculo debe estar provisto de una tapa, para evitar la entrada de polvo, fluidos o material extraño.'),
                    self::item('ntc_4_2_2', 'NTC 5212-1 Numeral 4.2.2', 'Es conveniente instalar el receptáculo en un lugar adecuado en el vehículo, que sea fácil de alcanzar, permitiendo así una operación segura. El lugar preferido es al lado del vehículo.'),
                ],
            ],
            'cilindros' => [
                'titulo' => 'Cilindros',
                'descripcion' => 'Instalación de cilindros',
                'icono' => 'heroicon-o-beaker',
                'columna' => 'Numeral NTC / Requisito',
                'items' => [
                    self::item('ntc_4_4_2', 'NTC 5212-1 Numeral 4.4.2', 'El cilindro de gas y los accesorios para montaje en el vehículo se deben construir de manera que los montajes no estén sujetos a falla por desgaste, corrosión (no se permite soldadura en los cilindros) o fatiga durante la vida en servicio del vehículo.'),
                    self::item('ntc_5_1_4', 'NTC 4821 Numeral 5.1.4', 'El sistema de sujeción de cada cilindro debe tener, como mínimo, dos soportes o herrajes (puntos de soporte) y debe estar diseñado para cumplir con el requisito establecido en el numeral 4.4 de la NTC 5212. El diseño debe estar respaldado por la presentación de memorias de cálculo.'),
                    self::item('ntc_5_1_5', 'NTC 4821 Numeral 5.1.5', 'Si se considera necesario reforzar el lugar del vehículo que se va a destinar para sujetar el o los cilindros, se puede hacer tal refuerzo ajustándolo a las condiciones de simetría y a la consideración descrita en el numeral 5.1.1.'),
                    self::item('ntc_5_1_6', 'NTC 4821 Numeral 5.1.6', 'El peso de los cilindros, cuando están llenos, no debe causar que el vehículo exceda su capacidad de peso bruto vehicular y no debe crear ninguna otra condición insegura en cuanto a distribución de cargas tanto en el eje principal como en las llantas.'),
                    self::item('ntc_5_1_7', 'NTC 4821 Numeral 5.1.7', 'En lo posible, se deben aprovechar los espacios libres de la carrocería o el chasis y colocarse los cilindros en fila o uno al lado del otro respetando siempre la simetría de peso del vehículo.'),
                    self::item('ntc_5_1_8', 'NTC 4821 Numeral 5.1.8', 'Los orificios para fijar los herrajes deben hacerse con taladro, nunca con oxicorte, para evitar debilitamiento del material.'),
                    self::item('ntc_5_1_9', 'NTC 4821 Numeral 5.1.9', 'Los cilindros deben ubicarse de tal forma que sea posible verificar la información del cilindro.'),
                    self::item('ntc_4_1_2_2', 'NTC 5212-1 Numeral 4.1.2.2', 'Los cilindros de gas deben estar equipados con válvulas de cilindro y dispositivos de alivio de presión, y deben ir montados de acuerdo con el numeral 4.4. Para evitar daño por calor, los cilindros de gas y accesorios deben usar una pantalla contra el calor, o estar colocados en relación con el sistema de escape, de manera que la temperatura de la pared no exceda el valor especificado por el fabricante del vehículo o del cilindro.'),
                    self::item('ntc_5_1_11', 'NTC 4821 Numeral 5.1.11', 'El dispositivo de fijación se debe proteger con un sistema de pintura contra la corrosión.'),
                    self::item('ntc_5_1_12', 'NTC 4821 Numeral 5.1.12', 'El material aislante debe evitar el contacto directo entre los dos metales y debe ser instalado firmemente entre el herraje y el cilindro. Se debería colocar una cinta adhesiva aislante en el cilindro en el sitio en que está en contacto con el caucho para prevenir la corrosión.'),
                    self::item('ntc_5_1_13', 'NTC 4821 Numeral 5.1.13', 'Los tornillos de la abrazadera (cincha) de sujeción del herraje y los cilindros deben ser fijados mediante un sistema de traba mecánica (doble tuerca o tuerca de seguridad) que evite el aflojamiento de las uniones.'),
                    self::item('ntc_5_1_14', 'NTC 4821 Numeral 5.1.14', 'Los cilindros Tipo 2, 3 y 4 (de acuerdo con lo contemplado en la NTC 3847) se deben proteger con una cubierta, para prevenir el daño por la salpicadura o posibles colisiones con objetos metálicos que salgan despedidos por las llantas.', ['inicial' => null]),
                    self::item('ntc_5_1_15', 'NTC 4821 Numeral 5.1.15', 'No debe soldarse ningún elemento al cilindro, y debe evitarse el contacto de éste con cualquier elemento metálico diferente al caucho que los separa del herraje.'),
                    self::item('ntc_5_1_16', 'NTC 4821 Numeral 5.1.16', 'Los cilindros deben estar montados de forma segura y ubicados dentro del perímetro del vehículo.'),
                    self::item('ntc_5_1_17', 'NTC 4821 Numeral 5.1.17', 'Los herrajes, al ser instalados, no deben presentar daños ocasionados por impactos ni fallas del material que pongan en peligro la condición de sujeción de los cilindros.'),
                    self::item('ntc_5_1_21', 'NTC 4821 Numeral 5.1.21', 'Cuando se instala un cilindro en el exterior de un vehículo, el cilindro debe cumplir las siguientes condiciones: a) no sobresalir fuera de los lados o extremos del vehículo; b) no sobresalir por encima del punto más alto de la estructura del vehículo; c) se deben ubicar las válvulas, conexiones y líneas rígidas (tuberías) de tal manera que se prevenga el daño debido al contacto con objetos estacionarios u otros objetos presentes en las vías; d) no se deben afectar negativamente las características de manejo del vehículo.', ['inicial' => null]),
                    self::item('ntc_5_1_23', 'NTC 4821 Numeral 5.1.23', 'El cilindro, cualquier accesorio o componente del sistema de fijación del cilindro, instalado bajo el chasis y delante del eje frontal, o detrás del eje trasero, deben estar ubicados más arriba de cualquier elemento estructural de la carrocería, chasis, motor o transmisión. Adicionalmente, el cilindro con su sistema de fijación y accesorios deben estar ubicados por encima del plano definido por: a) el punto donde la llanta delantera toca el suelo, hasta la parte inferior de la sección más sobresaliente del frente del vehículo; b) el punto donde la llanta trasera toca el suelo, hasta la parte inferior de la sección posterior más sobresaliente del vehículo.', ['inicial' => null]),
                    self::item('ntc_5_1_24', 'NTC 4821 Numeral 5.1.24', 'En la instalación de los cilindros se debe tener cuidado de no dejar elementos extraños alrededor de la estructura del vehículo; como tampoco se deben dejar tuercas o pernos o ambos en contacto con el cilindro.'),
                    self::item('ntc_5_1_25', 'NTC 4821 Numeral 5.1.25', 'Se debe realizar la instalación de modo que se facilite el llenado simultáneo de los cilindros.', ['inicial' => 'varios_cilindros']),
                    self::item('ntc_5_1_26', 'NTC 4821 Numeral 5.1.26', 'Los cilindros de gas deben estar provistos con una válvula manual, un dispositivo de alivio de presión y una válvula de exceso de flujo, que cumplan con la NTC 4830-5, NTC 4830-13 y NTC 4830-14 respectivamente.'),
                    self::item('ntc_5_1_28', 'NTC 4821 Numeral 5.1.28', 'Los cilindros que sean instalados delante del eje frontal o detrás del eje trasero del vehículo deben estar ubicados transversalmente. Si esto no es posible, se permite su ubicación longitudinal de modo que la válvula y los accesorios queden localizados en el extremo de los cilindros, más protegidos de posibles fuentes de impacto. No se permite la ubicación de cilindros en posición vertical.', ['inicial' => null]),
                    self::item('ntc_5_1_22', 'NTC 4821 Numeral 5.1.22', 'El cilindro, cualquier accesorio o componente del sistema de fijación del cilindro, instalado bajo el chasis y entre los ejes del vehículo, deben estar ubicados más arriba de cualquier elemento estructural de la carrocería, chasis, motor o caja de velocidades.', ['inicial' => null]),
                    self::item('ntc_4_1_2_2_fibra', 'NTC 5212-1 Numeral 4.1.2.2', 'Todos los cilindros de gas reforzados con fibra (Tipos 2, 3 y 4, de acuerdo con la NTC 3847 (ISO 11439)) se deben proteger contra la radiación ultravioleta.', ['inicial' => null]),
                ],
            ],
            'ventilacion' => [
                'titulo' => 'Ventilación',
                'descripcion' => 'Sistema de ventilación',
                'icono' => 'heroicon-o-arrows-pointing-out',
                'columna' => 'Numeral NTC / Requisito',
                'items' => [
                    self::item('ntc_4_3_4', 'NTC 5212-1 Numeral 4.3.4', 'Cualquier abertura para ventilación debe estar ubicada tan lejos como sea posible respecto a fuentes de ignición y en un sitio en el cual no sea susceptible de ser bloqueada.', ['inicial' => null]),
                    self::item('ntc_5_1_18', 'NTC 5212-1 Numeral 4.3.2 y NTC 4821 Numeral 5.1.18', 'Cuando un cilindro esté localizado dentro del compartimiento del conductor o de los pasajeros u otro compartimiento al interior del vehículo que no sea 100 % hermético, el extremo del cilindro que contiene la válvula y demás accesorios se debe encerrar dentro de una cubierta hermética con manguera de ventilación que cumpla la NTC 4830-15, y que garantice que cualquier fuga de gas es venteada directamente hacia el exterior del vehículo, mediante una abertura de ventilación permanente. Cuando la válvula del cilindro sea tipo auto contenedor o no requiera cubierta hermética solo se debe utilizar la manguera de ventilación.', ['inicial' => null]),
                    self::item('ntc_5_1_18_carga', 'NTC 5212-1 Numeral 4.3.3 y NTC 4821 Numeral 5.1.18', 'Cuando un cilindro esté localizado dentro de un compartimiento que no está diseñado para el transporte de pasajeros, el extremo del cilindro que contiene la válvula y demás accesorios se debe encerrar dentro de una cubierta hermética con manguera de ventilación que cumpla la NTC 4830-15, y que garantice que cualquier fuga de gas es venteada directamente hacia el exterior del vehículo mediante una abertura de ventilación permanente; o bien se debe sellar el compartimiento respecto al de pasajeros y disponer una abertura de ventilación con un área libre no menor de 1 035 mm², localizada en el nivel más alto posible del compartimiento. Cuando la válvula del cilindro sea tipo auto contenedor o no requiera cubierta hermética solo se debe utilizar la manguera de ventilación.', ['inicial' => null]),
                ],
            ],
            'selector' => [
                'titulo' => 'Selector',
                'descripcion' => 'Selector de combustible',
                'icono' => 'heroicon-o-arrows-right-left',
                'columna' => 'Numeral NTC / Requisito',
                'items' => [
                    self::item('ntc_5_6_1', 'NTC 4821 Numeral 5.6.1 y 5.6.2', 'El selector de combustible debe estar ubicado en la cabina del vehículo, al alcance del conductor, indicando el nivel de combustible en los cilindros y ubicado de tal modo que sea fácil efectuar su lectura.'),
                ],
            ],
            'indicador' => [
                'titulo' => 'Indicador',
                'descripcion' => 'Indicador de presión',
                'icono' => 'heroicon-o-chart-bar',
                'columna' => 'Numeral NTC / Requisito',
                'items' => [
                    self::item('ntc_5_8', 'NTC 4821 Numeral 5.8', 'El indicador de presión se debe instalar en un lugar visible, preferiblemente cerca de la válvula de carga.'),
                ],
            ],
            'dispositivo' => [
                'titulo' => 'Chip',
                'descripcion' => 'Dispositivo electrónico de grabación',
                'icono' => 'heroicon-o-cpu-chip',
                'columna' => 'Normativa / Requisito',
                'items' => [
                    self::item('res_29_1', 'Resolución 0957 Artículo 29.1', 'La instalación del dispositivo electrónico de identificación debe ubicarse en piezas fijas de la estructura del vehículo, observando un radio menor o igual a 30 cm desde la válvula de llenado, garantizando su visibilidad y accesibilidad para la conexión del lector.', ['medicion' => true, 'maximo' => 30]),
                    self::item('res_29_3', 'Resolución 0957 Artículo 29.3', 'El dispositivo de identificación del vehículo debe estar de acuerdo con la NTC 4829. Ingreso de la siguiente información dentro del dispositivo de identificación vehicular: 1. Número de identificación (IDROM); 2. Placa; 3. Nit de la empresa responsable centro de información; 4. Nit del taller convertidor; 5. Nit del organismo de certificación; 6. Fecha de certificación inicial; 7. Fecha de próxima certificación; 8. Fecha de última certificación.'),
                ],
            ],
        ];
    }

    /** Sin 'inicial' explícito, la respuesta sugerida es «Cumple». */
    private static function item(string $id, string $numeral, string $texto, array $extra = []): array
    {
        return ['id' => $id, 'numeral' => $numeral, 'texto' => $texto, 'inicial' => 'c', ...$extra];
    }

    /**
     * Respuestas sugeridas para una solicitud (prellenado). Solo trae los ítems que tienen
     * sugerencia; el evaluador puede cambiar cualquiera.
     *
     * @return array<string, string> id del ítem => 'c' | 'na'
     */
    public static function valoresIniciales(Solicitud $solicitud): array
    {
        $combustion = mb_strtolower((string) $solicitud->combustionSystem?->nombre);
        $tecnologia = mb_strtolower((string) $solicitud->technology?->nombre);
        $carburado = str_contains($combustion, 'carbur');
        $inyectado = str_contains($combustion, 'inyec');
        $cilindros = $solicitud->cilindros()->count();
        // Documentos que el taller sí cargó (sin el «Adjunto » ni la marca de obligatorio)
        $anexos = $solicitud->adjuntos()->whereNotNull('ruta_archivo')->pluck('nombre_adjunto')
            ->map(fn ($n) => mb_strtolower(trim(str_replace(['Adjunto ', ' *', ' Opcional'], '', $n))))->unique();
        $tiene = fn (string ...$nombres) => $anexos->intersect($nombres)->count() === count($nombres) ? 'c' : 'na';

        $reglas = [
            // Último regulador cerca al mezclador: aplica a carburados e inyectados de tercera generación
            'mezclador' => match (true) {
                $carburado, $inyectado && str_contains($tecnologia, 'tercera') => 'c',
                str_contains($tecnologia, 'quinta') => 'na',
                default => null,
            },
            'carburado' => match (true) {
                $carburado => 'c',
                $inyectado => 'na',
                default => null,
            },
            // Los anexos se sugieren según lo que subió el taller; el evaluador los confirma
            'anexo_prepos' => $tiene('preconversión', 'posconversión'),
            'anexo_acta' => $tiene('acta desmonte'),
            'anexo_ensayo' => $tiene('prueba hidrostática'),
            'varios_cilindros' => match (true) {
                $cilindros > 1 => 'c',
                $cilindros === 1 => 'na',
                default => null,
            },
        ];

        $valores = [];
        foreach (self::secciones() as $seccion) {
            foreach ($seccion['items'] as $item) {
                $valor = array_key_exists((string) $item['inicial'], $reglas) ? $reglas[$item['inicial']] : $item['inicial'];
                if ($valor !== null) {
                    $valores[$item['id']] = $valor;
                }
            }
        }

        return $valores;
    }

    /** Las 5 ubicaciones de la plataforma anterior; el orden es el número que usan las reglas. */
    public const UBICACIONES_CILINDRO = [
        'chasis_ejes' => 'Debajo del chasis, entre ejes',
        'chasis_prolongacion' => 'Debajo del chasis, delante o atrás de los ejes',
        'exterior' => 'Exterior (ej. platón, estacas)',
        'baul' => 'Compartimiento de carga o maletero',
        'pasajeros' => 'Compartimiento de pasajeros',
    ];

    /** Ítems que responde la ubicación del cilindro, en el orden de las columnas de REGLAS_UBICACION. */
    public const ITEMS_UBICACION = ['ntc_5_1_21', 'ntc_5_1_22', 'ntc_5_1_23', 'ntc_5_1_28', 'ntc_5_1_18', 'ntc_5_1_18_carga', 'ntc_4_3_4'];

    /**
     * Respuesta de cada ítem de ITEMS_UBICACION según las ubicaciones marcadas (tabla literal de la
     * plataforma anterior). c = cumple, n = no aplica, - = sin regla: lo contesta el evaluador.
     */
    public const REGLAS_UBICACION = [
        '1' => 'ccnnnnn', '2' => 'cnccnnn', '3' => 'ccnnnnn', '4' => 'nnnnncc', '5' => 'nnnncnc',
        '1+2' => 'ccccnnn', '1+3' => 'cc--nnn', '1+4' => 'ccnnncc', '1+5' => 'ccnncnc', '2+3' => 'c-ccnnn',
        '2+4' => 'cnccncc', '2+5' => 'cncccnc', '3+4' => 'c---ncc', '3+5' => 'c---cnc', '4+5' => 'nnnnccc',
        '1+2+3' => 'ccccnnn', '1+2+4' => 'ccccncc', '1+2+5' => 'cccccnc', '1+3+4' => 'cc--ncc', '1+3+5' => 'cc--cnc',
        '1+4+5' => 'ccnnccc', '2+3+4' => 'c-ccncc', '2+3+5' => 'c-cccnc', '2+4+5' => 'cnccccc', '3+4+5' => 'c---ccc',
        '1+2+3+4' => 'ccccncc', '1+2+3+5' => 'cccccnc', '1+2+4+5' => 'ccccccc', '1+3+4+5' => 'cc--ccc', '2+3+4+5' => 'c-ccccc',
        '1+2+3+4+5' => 'ccccccc',
    ];

    /** Ítems que responde «¿Es ultraliviano?»: Sí = cumple, No = no aplica. */
    public const ITEMS_ULTRALIVIANO = ['ntc_4_1_2_2_fibra', 'ntc_5_1_14'];

    /** Un vehículo dedicado a gas no tiene gasolina ni selector: siempre «No aplica». */
    public const ITEMS_DEDICADO = ['ntc_5_4_5', 'ntc_5_6_1'];

    public static function esDedicado(Solicitud $solicitud): bool
    {
        return str_contains(mb_strtolower((string) $solicitud->applicationType?->nombre), 'dedicado');
    }

    /**
     * Respuestas que se fijan solas según lo que el evaluador marca sobre los cilindros (una sola
     * vez por solicitud, como en la plataforma anterior) y el tipo de aplicación.
     * null = el ítem queda sin respuesta; un ítem ausente no se toca.
     *
     * @param  ?string  $ultraLiviano  'si' | 'no' | null (sin contestar)
     * @param  array<int, string>  $ubicaciones  claves de UBICACIONES_CILINDRO
     * @return array<string, ?string> id del ítem => 'c' | 'na' | null
     */
    public static function reglasDinamicas(?string $ultraLiviano, array $ubicaciones, bool $dedicado): array
    {
        $valores = [];
        $numeros = array_flip(array_keys(self::UBICACIONES_CILINDRO));

        $marcadas = collect($ubicaciones)
            ->filter(fn ($u) => is_string($u) && isset($numeros[$u]))
            ->map(fn ($u) => $numeros[$u] + 1)->unique()->sort()->implode('+');

        if ($marcadas !== '') {
            foreach (self::ITEMS_UBICACION as $i => $id) {
                $valores[$id] = ['c' => 'c', 'n' => 'na', '-' => null][self::REGLAS_UBICACION[$marcadas][$i]];
            }
        }

        if (in_array($ultraLiviano, ['si', 'no'], true)) {
            $valores += array_fill_keys(self::ITEMS_ULTRALIVIANO, $ultraLiviano === 'si' ? 'c' : 'na');
        }

        if ($dedicado) {
            $valores = array_fill_keys(self::ITEMS_DEDICADO, 'na') + $valores;
        }

        return $valores;
    }

    /** Ítems marcados como "No cumple" (para alertar al evaluador y al revisor). */
    public static function noConformidades(array $datos): array
    {
        $nc = [];

        foreach (self::secciones() as $seccion) {
            foreach ($seccion['items'] as $item) {
                if (($datos[$item['id']] ?? null) === 'nc') {
                    $nc[] = $item['numeral'].' — '.$item['texto'];
                }
            }
        }

        return $nc;
    }
}
