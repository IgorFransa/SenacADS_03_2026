document.getElementById('loginForm').addEventListener('submit', async function (event) {
    event.preventDefault();

    var form = event.currentTarget;
    var botao = form.querySelector('button[type="submit"]');
    var erro = document.getElementById('loginErro');

    erro.hidden = true;
    botao.disabled = true;

    try {
        var resposta = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin'
        });
        var dados = await resposta.json();

        if (!resposta.ok) {
            throw new Error(dados.erro || 'Não foi possível entrar no portal.');
        }

        window.location.href = dados.redirect;
    } catch (erroLogin) {
        erro.textContent = erroLogin.message;
        erro.hidden = false;
        botao.disabled = false;
    }
});
