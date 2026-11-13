'use strict';

const signUpButton = document.getElementById('signUp');
const signInButton = document.getElementById('signIn');
const mobileLogin = document.getElementById('auth-ir-login');
const mobileCadastro = document.getElementById('auth-ir-cadastro');
const container = document.getElementById('container');

if (container) {
    function alternarCadastro(abrir) {
        container.classList.toggle('right-panel-active', abrir);
        if (mobileLogin) mobileLogin.setAttribute('aria-pressed', String(!abrir));
        if (mobileCadastro) mobileCadastro.setAttribute('aria-pressed', String(abrir));
    }

    if (signUpButton) signUpButton.addEventListener('click', () => alternarCadastro(true));
    if (signInButton) signInButton.addEventListener('click', () => alternarCadastro(false));
    if (mobileCadastro) mobileCadastro.addEventListener('click', () => alternarCadastro(true));
    if (mobileLogin) mobileLogin.addEventListener('click', () => alternarCadastro(false));
}
