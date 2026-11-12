{{-- Recuadro de datos: $filas = ['Etiqueta' => 'valor', ...]. Un valor puede ser ['texto', '#color']. --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 22px; border-radius:12px; background:#f8f6f6; border:1px solid #efe9e9;">
    @foreach ($filas as $etiqueta => $valor)
        @php [$texto, $color] = is_array($valor) ? $valor : [$valor, '#2f2427']; @endphp
        <tr>
            <td style="padding:12px 18px; {{ $loop->last ? '' : 'border-bottom:1px solid #efe9e9;' }}">
                <span style="display:block; font-size:11px; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:#8d8184;">{{ $etiqueta }}</span>
                <span style="display:block; margin-top:2px; font-size:17px; font-weight:700; color:{{ $color }};">{{ $texto }}</span>
            </td>
        </tr>
    @endforeach
</table>
