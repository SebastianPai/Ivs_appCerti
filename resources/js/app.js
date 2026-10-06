import "./bootstrap";

window.getCoordinates = function () {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) return reject("No soportado");

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                resolve({
                    lat: pos.coords.latitude,
                    lng: pos.coords.longitude,
                    accuracy: pos.coords.accuracy,
                });
            },
            (err) => reject(err),
            {
                enableHighAccuracy: true, // Forzamos GPS de alta precisión
                timeout: 10000, // Máximo 10 segundos de espera
                maximumAge: 0, // No usar caché, queremos ubicación real
            },
        );
    });
};
