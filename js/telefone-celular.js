/**
 * AgenDay 2.0 — máscara dos telefones e celulares.
 *
 * Limita a digitação a 11 dígitos (DDD + 9 + 8) e formata como
 * "(92) 99999-9999". Campos marcados com data-permitir-fixo aceitam de 8 a 11
 * dígitos, para não travar a edição de cadastros antigos com número incompleto.
 */
(function () {
    'use strict';

    var MAX_DIGITOS = 11;

    function apenasDigitos(valor) {
        var digitos = String(valor || '').replace(/\D/g, '');
        // Colagem no formato internacional: +55 (92) 99999-9999.
        if (digitos.length > MAX_DIGITOS && digitos.slice(0, 2) === '55') {
            digitos = digitos.slice(2);
        }
        return digitos.slice(0, MAX_DIGITOS);
    }

    function formatar(digitos) {
        if (digitos.length === 0) {
            return '';
        }
        if (digitos.length <= 2) {
            return '(' + digitos;
        }
        var ddd = digitos.slice(0, 2);
        var resto = digitos.slice(2);
        if (resto.length <= 4) {
            return '(' + ddd + ') ' + resto;
        }
        if (resto.length <= 8) {
            return '(' + ddd + ') ' + resto.slice(0, 4) + '-' + resto.slice(4);
        }
        return '(' + ddd + ') ' + resto.slice(0, 5) + '-' + resto.slice(5);
    }

    function validar(campo) {
        var digitos = apenasDigitos(campo.value);
        if (digitos.length === 0) {
            campo.setCustomValidity('');
            return;
        }
        if (campo.hasAttribute('data-permitir-fixo')) {
            campo.setCustomValidity(digitos.length >= 8 && digitos.length <= 11
                ? ''
                : 'Informe o número com DDD (8 a 11 dígitos).');
            return;
        }
        campo.setCustomValidity(digitos.length === 11
            ? ''
            : 'Informe os 11 dígitos do celular com DDD.');
    }

    function aplicar(campo) {
        if (campo.dataset.telefoneCelularPronto === '1') {
            return;
        }
        campo.dataset.telefoneCelularPronto = '1';
        campo.setAttribute('inputmode', 'numeric');

        campo.addEventListener('input', function () {
            campo.value = formatar(apenasDigitos(campo.value));
            validar(campo);
        });

        campo.addEventListener('blur', function () {
            campo.value = formatar(apenasDigitos(campo.value));
            validar(campo);
        });
    }

    function iniciar() {
        document.querySelectorAll('input[type="tel"]').forEach(aplicar);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
