const boton = document.querySelector('#verPassword');
boton.addEventListener('click', () => {
    const input = document.querySelector('#password');
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    boton.textContent = visible ? 'Ocultar' : 'Mostrar';
    boton.setAttribute('aria-pressed', String(visible));
});
document.querySelector('.login-form').addEventListener('submit', () => {
    const submit = document.querySelector('.login-submit');
    submit.disabled = true; submit.textContent = 'Ingresando…';
});
