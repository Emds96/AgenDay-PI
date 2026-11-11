'use strict';

// Microtarefa 4.2: visualização de uma prévia. Nenhum envio é realizado.
(() => {
    const formulario = document.querySelector('#form-novo-agendamento');
    if (!formulario) return;

    // Impede envio também quando o visitante pressiona Enter.
    // O servidor rejeita métodos POST enquanto a etapa 4.3 não existir.
    formulario.addEventListener('submit', (evento) => evento.preventDefault());

    const cliente = document.querySelector('#agendamento-cliente');
    const servico = document.querySelector('#agendamento-servico');
    const funcionario = document.querySelector('#agendamento-funcionario');
    const data = document.querySelector('#agendamento-data');
    const hora = document.querySelector('#agendamento-hora');
    const aviso = document.querySelector('#agendamento-aviso-funcionario');
    const resumo = document.querySelector('#agendamento-resumo');
    const pares = document.querySelector('#agendamento-pares');
    if (!cliente || !servico || !funcionario || !data || !hora || !aviso || !resumo || !pares) return;

    let vinculos = [];
    try {
        const carregados = JSON.parse(pares.textContent);
        if (Array.isArray(carregados)) {
            vinculos = carregados.filter(item =>
                Number.isSafeInteger(item.id_funcionario)
                && Number.isSafeInteger(item.id_servicos)
                && typeof item.nome_funcionario === 'string');
        }
    } catch (_) {
        aviso.textContent = 'Não foi possível carregar os vínculos dos funcionários.';
    }

    const pad = numero => String(numero).padStart(2, '0');
    const hoje = new Date();
    data.min = hoje.getFullYear() + '-' + pad(hoje.getMonth() + 1) + '-' + pad(hoje.getDate());

    function textoOpcao(select) {
        return select.selectedIndex > 0 ? select.options[select.selectedIndex].text.trim() : '';
    }

    function atualizarResumo() {
        const nomeCliente = textoOpcao(cliente);
        const nomeServico = textoOpcao(servico);
        const nomeFuncionario = textoOpcao(funcionario);
        if (!nomeCliente || !nomeServico || !nomeFuncionario || !data.value || !hora.value
            || !data.checkValidity() || !hora.checkValidity()) {
            resumo.textContent = 'Selecione cliente, serviço, funcionário, data e horário para visualizar a previsão.';
            return;
        }

        const duracao = servico.selectedOptions[0].dataset.duracao || '';
        const partes = /^(\d{1,3}):([0-5]\d):([0-5]\d)$/.exec(duracao);
        if (!partes) {
            resumo.textContent = 'A duração do serviço precisa ser revisada.';
            return;
        }
        const minutos = Number(partes[1]) * 60 + Number(partes[2]);
        if (minutos < 1 || minutos > 1440 || Number(partes[3]) !== 0) {
            resumo.textContent = 'A duração do serviço precisa ser revisada.';
            return;
        }

        const inicio = new Date(data.value + 'T' + hora.value + ':00');
        if (!Number.isFinite(inicio.getTime()) || inicio < new Date()) {
            resumo.textContent = 'Escolha uma data e um horário futuros válidos.';
            return;
        }
        const fim = new Date(inicio.getTime() + minutos * 60000);
        const formatador = new Intl.DateTimeFormat('pt-BR', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
        resumo.textContent = 'Prévia (não salva): '
            + nomeCliente + ' · ' + nomeServico + ' · ' + nomeFuncionario
            + ' · início ' + formatador.format(inicio)
            + ' · fim previsto ' + formatador.format(fim)
            + ' (' + minutos + ' min). A disponibilidade não foi verificada.';
    }

    function atualizarFuncionarios() {
        const escolhido = Number(servico.value);
        funcionario.replaceChildren(new Option('Selecione um funcionário', ''));
        funcionario.disabled = true;

        if (!servico.value) {
            aviso.textContent = 'Primeiro selecione um serviço.';
            atualizarResumo();
            return;
        }

        const elegiveis = vinculos.filter(item => item.id_servicos === escolhido);
        for (const item of elegiveis) {
            funcionario.add(new Option(item.nome_funcionario, String(item.id_funcionario)));
        }
        if (!elegiveis.length) {
            aviso.textContent = 'Nenhum funcionário está vinculado a este serviço.';
        } else {
            funcionario.disabled = false;
            aviso.textContent = elegiveis.length + ' funcionário(s) vinculado(s). Isso não confirma horário livre.';
        }
        atualizarResumo();
    }

    servico.addEventListener('change', atualizarFuncionarios);
    for (const campo of [cliente, funcionario, data, hora]) {
        campo.addEventListener('change', atualizarResumo);
    }
    data.addEventListener('input', atualizarResumo);
    hora.addEventListener('input', atualizarResumo);
    atualizarFuncionarios();
})();
