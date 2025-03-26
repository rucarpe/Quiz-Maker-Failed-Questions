(function($) {
    'use strict';

    $(document).ready(function() {
        // Cualquier inicialización necesaria
        
        // Función para mostrar notificaciones
        function showNotification(message, isError) {
            // Crear elemento de notificación si no existe
            var $notification = $('.ays-quiz-fq-notification');
            if ($notification.length === 0) {
                $notification = $('<div class="ays-quiz-fq-notification"></div>');
                $('body').append($notification);
            }
            
            // Establecer mensaje y clase de error si es necesario
            $notification.text(message);
            $notification.toggleClass('error', isError === true);
            
            // Mostrar notificación
            $notification.addClass('show');
            
            // Ocultar después de 5 segundos
            setTimeout(function() {
                $notification.removeClass('show');
            }, 5000);
        }
    });

})(jQuery);