<?php

// Mensajes de validación en español (Laravel solo trae inglés por defecto).

return [
    'accepted' => 'Debe aceptar :attribute.',
    'after' => ':Attribute debe ser una fecha posterior a :date.',
    'after_or_equal' => ':Attribute debe ser una fecha posterior o igual a :date.',
    'alpha_num' => ':Attribute solo puede contener letras y números.',
    'array' => ':Attribute debe ser una lista.',
    'before' => ':Attribute debe ser una fecha anterior a :date.',
    'before_or_equal' => ':Attribute debe ser una fecha anterior o igual a :date.',
    'between' => [
        'array' => ':Attribute debe tener entre :min y :max elementos.',
        'file' => ':Attribute debe pesar entre :min y :max kilobytes.',
        'numeric' => ':Attribute debe estar entre :min y :max.',
        'string' => ':Attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean' => ':Attribute debe ser verdadero o falso.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'date' => ':Attribute no es una fecha válida.',
    'different' => ':Attribute y :other deben ser diferentes.',
    'digits' => ':Attribute debe tener :digits dígitos.',
    'digits_between' => ':Attribute debe tener entre :min y :max dígitos.',
    'distinct' => ':Attribute está repetido.',
    'email' => ':Attribute debe ser un correo electrónico válido.',
    'exists' => 'El valor seleccionado en :attribute no es válido.',
    'file' => ':Attribute debe ser un archivo.',
    'gt' => [
        'numeric' => ':Attribute debe ser mayor que :value.',
    ],
    'gte' => [
        'numeric' => ':Attribute debe ser mayor o igual que :value.',
    ],
    'image' => ':Attribute debe ser una imagen.',
    'in' => 'El valor seleccionado en :attribute no es válido.',
    'integer' => ':Attribute debe ser un número entero.',
    'lt' => [
        'numeric' => ':Attribute debe ser menor que :value.',
    ],
    'lte' => [
        'numeric' => ':Attribute debe ser menor o igual que :value.',
    ],
    'max' => [
        'array' => ':Attribute no debe tener más de :max elementos.',
        'file' => ':Attribute no debe pesar más de :max kilobytes.',
        'numeric' => ':Attribute no debe ser mayor que :max.',
        'string' => ':Attribute no debe tener más de :max caracteres.',
    ],
    'mimes' => ':Attribute debe ser un archivo de tipo: :values.',
    'mimetypes' => ':Attribute debe ser un archivo de tipo: :values.',
    'min' => [
        'array' => ':Attribute debe tener al menos :min elementos.',
        'file' => ':Attribute debe pesar al menos :min kilobytes.',
        'numeric' => ':Attribute debe ser al menos :min.',
        'string' => ':Attribute debe tener al menos :min caracteres.',
    ],
    'not_in' => 'El valor de :attribute no está permitido.',
    'numeric' => ':Attribute debe ser un número.',
    'present' => ':Attribute debe estar presente.',
    'regex' => 'El formato de :attribute no es válido.',
    'required' => ':Attribute es obligatorio.',
    'required_if' => ':Attribute es obligatorio cuando :other es :value.',
    'required_with' => ':Attribute es obligatorio cuando :values está presente.',
    'same' => ':Attribute y :other deben coincidir.',
    'size' => [
        'numeric' => ':Attribute debe ser :size.',
        'string' => ':Attribute debe tener :size caracteres.',
    ],
    'string' => ':Attribute debe ser texto.',
    'unique' => 'Ya existe un registro con este :attribute.',
    'uploaded' => 'No se pudo subir :attribute. Revise el tamaño del archivo.',
    'url' => ':Attribute debe ser una URL válida.',

    'custom' => [],

    'attributes' => [
        'email' => 'correo',
        'password' => 'contraseña',
        'name' => 'nombre',
    ],
];
