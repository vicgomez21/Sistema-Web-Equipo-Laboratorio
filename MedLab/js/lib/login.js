const boton = document.querySelector('#verPassword');
boton.addEventListener('click', () => {
    const input = document.querySelector('#password');
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    boton.textContent = visible ? 'Ocultar' : 'Mostrar';
    boton.setAttribute('aria-pressed', String(visible));
});

const form = document.querySelector('.login-form');
const submit = document.querySelector('.login-submit');
form.addEventListener('submit', () => {
    submit.disabled = true;
    submit.textContent = 'Ingresando…';
});
window.addEventListener('pageshow', () => {
    submit.disabled = false;
    submit.innerHTML = 'Iniciar sesión <span aria-hidden="true">→</span>';
});
