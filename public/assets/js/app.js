/* ============================================================
   DIEGO GOURMET — V4
   Integração API REST com o frontend SPA
   ============================================================ */

const API = '/api';
let TOKEN = localStorage.getItem('dg_jwt') || null;

/* ─── Helper central de fetch ───────────────────────────── */
async function api(method, path, body = null) {
    try {
        const opts = {
            method: method.toUpperCase(),
            headers: {
                'Content-Type': 'application/json',
                ...(TOKEN ? { 'Authorization': `Bearer ${TOKEN}` } : {})
            }
        };
        if (body && method !== 'GET') opts.body = JSON.stringify(body);
        const res = await fetch(API + path, opts);
        const json = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(json.error || `HTTP ${res.status}`);
        return json.data ?? json;
    } catch (err) {
        console.error(`[API ${method} ${path}]`, err.message);
        throw err;
    }
}

async function apiUpload(file, prefix = 'prod') {
    const fd = new FormData();
    fd.append('arquivo', file);
    const res = await fetch(`${API}/upload/imagem.php?prefix=${prefix}`, {
        method: 'POST',
        headers: TOKEN ? { 'Authorization': `Bearer ${TOKEN}` } : {},
        body: fd
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(json.error || 'Falha no upload');
    return json.data;
}

/* ─── Mapeadores backend → formato JS do HTML ───────────── */
const _mapProdutos = ps => (ps || []).map(p => ({
    id: +p.id, ativo: p.ativo == 1, ordem: +p.ordem_vitrine,
    cat: p.categoria_nome, nome: p.nome, grid: p.grid_vitrine, img: p.imagem_url,
    descricao: p.descricao || '', nutricional: p.nutricional || '', preparo: p.modo_preparo || '',
    opcoes: (p.opcoes || []).map(o => ({ nome: o.nome, preco: +o.preco, mult: +o.multiplicador })),
    receita: (p.receita || []).map(r => ({ id: r.insumo_id, qtd: +r.quantidade })),
    permiteEncomenda: p.permite_encomenda, sinalMinimo: +p.sinal_minimo_perc, antecedenciaMin: +p.antecedencia_min_dias,
}));

const _mapEstoque = ins => (ins || []).map(i => ({
    id: i.id, nome: i.nome, custo_unid: +i.custo_unitario,
    capMax: +i.capacidade_max, unid: i.unidade,
    lotes: (i.lotes || []).map(l => ({
        idLote: l.id, qtd: +l.quantidade,
        dt_inclusao: l.data_inclusao, dt_vencimento: l.data_vencimento,
    })),
}));

const _mapClientes = cs => (cs || []).map(c => ({
    idCli: +c.id, nome: c.nome, telefone: c.telefone, endereco: c.endereco,
    senha: '(oculta)', dataNascimento: c.data_nascimento,
    preferencias: c.preferencias || [], dataCadastro: c.data_cadastro,
}));

function _mapPedidos(ps) {
    const vendas = [];
    (ps || []).forEach(p => {
        (p.itens || []).forEach(item => {
            vendas.push({
                idReq: p.id, idProd: +item.produto_id,
                data: p.data_pedido, dataEntrega: p.data_entrega,
                criadoEm: p.criado_em || null,
                horaEntrega: (p.hora_entrega || '12:00:00').substring(0, 5),
                valor: +item.valor_unitario, valorLiquido: +item.valor_liquido,
                custo: +item.custo_insumos, alergias: p.alergias || '',
                obs: [item.personalizacao, p.observacoes].filter(Boolean).join(' | '),
                idCli: +p.usuario_id || null, clienteNome: p.cliente_nome,
                clienteEnd: p.cliente_endereco, telefone: p.cliente_telefone,
                tipoVenda: p.tipo_venda, formaPagamento: p.forma_pagamento,
                taxaPagamento: +p.taxa_pagamento_perc, sinalPago: +p.sinal_pago,
                saldoDevedor: +p.saldo_devedor, statusPagamento: p.status_pagamento,
                statusPedido: p.status_pedido, respostaAdmin: p.resposta_admin || '',
                personalizacao: item.personalizacao, variacaoNome: item.variacao_nome,
            });
        });
    });
    return vendas;
}

function _mapConfig(cfg) {
    if (!cfg) return;
    const t = cfg.taxas || {};
    dbConfig.taxaDebito = +(t.taxa_debito || 1.99);
    dbConfig.taxaCredito = +(t.taxa_credito || 3.49);
    dbConfig.taxaPix = +(t.taxa_pix || 0);
    dbConfig.taxaDinheiro = +(t.taxa_dinheiro || 0);
    dbConfig.prazoRecebimentoCredito = +(t.prazo_recebimento_credito || 30);
    dbConfig.politicaTaxa = t.politica_taxa || 'manual';
    dbConfig.custosFixos = (cfg.custos_fixos || []).map(c => ({
        id: +c.id, nome: c.nome, valor: +c.valor, ativo: c.ativo == 1
    }));
    dbConfig.regrasFidelidade = (cfg.fidelidade || []).map(r => ({
        id: +r.id, nome: r.nome, tipo: r.tipo, valor: +r.valor_meta,
        produtoId: r.produto_id ? +r.produto_id : null, ativo: r.ativo == 1,
    }));
    dbConfig.datasBloqueadas = cfg.datas_bloqueadas || [];
    if (Array.isArray(cfg.tipos_fidelidade) && cfg.tipos_fidelidade.length) {
        dbTiposFidelidade = cfg.tipos_fidelidade;
    }
}

/* ─── Carregamento da vitrine (público) ─────────────────── */
async function carregarVitrine() {
    try {
        const [produtos, categorias] = await Promise.all([
            api('GET', '/produtos/index.php'),
            api('GET', '/categorias/index.php'),
        ]);
        dbProdutos = _mapProdutos(produtos);
        dbCategorias = (categorias || []).map(c => c.nome);
    } catch (e) {
        if (typeof mostrarToast === 'function') mostrarToast('Backend offline.', 'warning');
    }
}

/* ─── Carregamento de dados administrativos ─────────────── */
async function carregarDadosAdmin() {
    try {
        const [pedidos, estoque, cfg, clientes] = await Promise.all([
            api('GET', '/pedidos/index.php'),
            api('GET', '/estoque/index.php'),
            api('GET', '/configuracoes/index.php'),
            api('GET', '/clientes/index.php'),
        ]);
        dbVendas = _mapPedidos(pedidos);
        dbEstoque = _mapEstoque(estoque);
        dbClientes = _mapClientes(clientes);
        _mapConfig(cfg);

        dbEstoqueAlocado = {};
        dbVendas.forEach(v => {
            if (v.tipoVenda === 'encomenda' && v.statusPedido === 'Recebido') {
                const p = dbProdutos.find(x => x.id === v.idProd);
                if (p) {
                    const op = (p.opcoes || []).find(o => o.nome === v.variacaoNome) || (p.opcoes && p.opcoes[0]);
                    if (op && p.receita) {
                        p.receita.forEach(r => {
                            const q = r.qtd * (op.mult || 1);
                            dbEstoqueAlocado[r.id] = (dbEstoqueAlocado[r.id] || 0) + q;
                        });
                    }
                }
            }
        });
        verificarAlertas();
        verificarVolumePedidos();
    } catch (e) { console.error('Erro admin:', e); }
}

/* ─── Alerta de volume de pedidos ────────────────────────── */
let _ultimoAlertaVolume = 0;

function verificarVolumePedidos(aoFazerLogin = false) {
    if (perfilAtivo !== 'admin') return;

    const agora = Date.now();
    const umaHoraAtras = new Date(agora - 60 * 60 * 1000);

    const idsUltimaHora = [...new Set(
        dbVendas.filter(v => {
            if (!v.criadoEm) return false;
            const dt = new Date(v.criadoEm.replace(' ', 'T'));
            return dt >= umaHoraAtras;
        }).map(v => v.idReq)
    )];

    const cooldownOk = aoFazerLogin || (agora - _ultimoAlertaVolume) > 5 * 60 * 1000;

    if (idsUltimaHora.length >= 10 && cooldownOk) {
        _ultimoAlertaVolume = agora;
        if (typeof adicionarNotificacao === 'function') {
            adicionarNotificacao(
                'alerta',
                'Volume alto de pedidos!',
                `${idsUltimaHora.length} pedidos recebidos na última hora. Verifique a fila de produção.`,
                null
            );
        }
    }
}

/* ─── Carregamento do cliente logado ─────────────────────── */
async function carregarDadosCliente() {
    try {
        const pedidos = await api('GET', '/pedidos/historico-cliente.php');
        const meus = _mapPedidos(pedidos);
        const id = clienteLogado?.idCli;
        dbVendas = [...dbVendas.filter(v => v.idCli !== id), ...meus];
        if (typeof renderizarAreaCliente === 'function') renderizarAreaCliente();
    } catch (e) { console.error('Erro cliente:', e); }
}

/* ============================================================
   FUNÇÕES SOBRESCRITAS (substituem in-memory por API)
   ============================================================ */

/* LOGIN */
window.realizarLogin = async function (tipo) {
    const inLogin = document.getElementById('login-' + tipo);
    const inPass = document.getElementById('pass-' + tipo);
    const login = inLogin ? inLogin.value.trim() : '';
    const senha = inPass.value;
    if (!login || !senha) { mostrarToast('Preencha login e senha.', 'error'); return; }
    try {
        const data = await api('POST', '/auth/login.php', { login, senha });
        TOKEN = data.token;
        localStorage.setItem('dg_jwt', TOKEN);
        const u = data.usuario;
        document.getElementById('btn-login-header').style.display = 'none';
        document.getElementById('btn-nav-logout').style.display = 'inline-flex';

        if (u.perfil === 'admin' || u.perfil === 'cozinha') {
            perfilAtivo = 'admin';
            document.getElementById('btn-nav-admin').style.display = 'inline-flex';
            mostrarToast('Gestão Desbloqueada.');
            await carregarDadosAdmin();
            switchView('hub-admin');
            verificarVolumePedidos(true);
        } else {
            clienteLogado = {
                idCli: u.id, nome: u.nome, telefone: u.telefone,
                endereco: u.endereco, preferencias: u.preferencias || []
            };
            perfilAtivo = 'cliente';
            document.getElementById('btn-nav-cliente').style.display = 'inline-flex';
            document.getElementById('lbl-cliente-nome').innerText = `Olá, ${u.nome.split(' ')[0]}`;
            mostrarToast(`Bem-vindo, ${u.nome}!`);
            await carregarDadosCliente();
            switchView('vitrine');
        }
    } catch (e) {
        mostrarToast(e.message || 'Credenciais incorretas.', 'error');
    }
    if (inPass) inPass.value = '';
};

window.logout = function () {
    TOKEN = null;
    localStorage.removeItem('dg_jwt');
    clienteLogado = null;
    perfilAtivo = null;
    document.getElementById('btn-login-header').style.display = 'inline-flex';
    document.getElementById('btn-nav-cliente').style.display = 'none';
    document.getElementById('btn-nav-admin').style.display = 'none';
    document.getElementById('btn-nav-logout').style.display = 'none';
    document.getElementById('alerts-fab').style.display = 'none';
    document.getElementById('alerts-panel').classList.remove('open');
    mostrarToast('Logout realizado.');
    switchView('vitrine');
};

/* CADASTRO */
window.cadastrarCliente = async function () {
    const n    = document.getElementById('cad-nome').value.trim();
    const tel  = document.getElementById('cad-tel').value.replace(/\D/g, '');
    const end  = document.getElementById('cad-end').value.trim();
    const pass = document.getElementById('cad-senha').value;
    const bday = document.getElementById('cad-bday')?.value || null;
    const prefs = Array.from(document.querySelectorAll('#cad-preferencias .chip.active')).map(c => c.dataset.pref);

    if (!n || !tel || !end || !pass) { mostrarToast('Preencha todos os campos obrigatórios.', 'error'); return; }
    if (tel.length !== 11) { mostrarToast('Telefone deve ter exatamente 11 dígitos com DDD (ex: 31999999999).', 'error'); return; }
    if (pass.length < 8) { mostrarToast('A senha deve ter pelo menos 8 caracteres.', 'error'); return; }
    if (!/[A-Z]/.test(pass)) { mostrarToast('A senha deve conter pelo menos uma letra maiúscula.', 'error'); return; }
    if (!/[$#@!%*?&]/.test(pass)) { mostrarToast('A senha deve conter pelo menos um símbolo: $ # @ ! % * ? &', 'error'); return; }

    try {
        await api('POST', '/auth/register.php', {
            nome: n, telefone: tel, endereco: end, senha: pass,
            data_nascimento: bday, preferencias: prefs
        });
        document.getElementById('modal-cadastro').style.display = 'none';
        ['cad-nome','cad-tel','cad-end','cad-senha','cad-bday'].forEach(id => {
            const el = document.getElementById(id); if (el) el.value = '';
        });
        mostrarToast('Cadastro realizado! Faça login.');
    } catch (e) { mostrarToast(e.message, 'error'); }
};

/* PERFIL VIP */
window.salvarPerfilVIP = async function () {
    if (!clienteLogado) return;
    const n = document.getElementById('vip-nome').value.trim();
    const t = document.getElementById('vip-tel').value.trim();
    const e = document.getElementById('vip-end').value.trim();
    const s1 = document.getElementById('vip-senha').value.trim();
    const s2 = document.getElementById('vip-senha-conf').value.trim();
    if (!n || !t || !e) { mostrarToast('Preencha todos os campos.', 'error'); return; }
    if (s1 && s1 !== s2) { mostrarToast('Senhas não coincidem.', 'error'); return; }
    const body = { nome: n, telefone: t, endereco: e, preferencias: clienteLogado.preferencias || [] };
    if (s1) { body.nova_senha = s1; body.confirmar_senha = s2; }
    try {
        await api('PUT', '/clientes/atualizar-perfil.php', body);
        Object.assign(clienteLogado, { nome: n, telefone: t, endereco: e });
        document.getElementById('lbl-cliente-nome').innerText = `Olá, ${n.split(' ')[0]}`;
        fecharModal('modal-perfil-vip');
        mostrarToast('Perfil atualizado!');
        renderizarAreaCliente();
    } catch (e) { mostrarToast(e.message, 'error'); }
};

/* CHECKOUT */
window.processarCompraCart = async function () {
    const nome = document.getElementById('chk-nome').value.trim();
    const modalidade = document.querySelector('input[name="chk-modalidade"]:checked').value;
    const endereco = modalidade === 'retirada' ? 'Retirada na Loja' : document.getElementById('chk-endereco').value.trim();
    const telefone = document.getElementById('chk-tel').value.trim();
    const alergias = document.getElementById('chk-alergias').value.trim();
    const obs = document.getElementById('chk-obs-geral')?.value.trim() || '';
    const forma = document.getElementById('chk-forma-pgto').value;
    const repassa = document.getElementById('chk-repassa-taxa').value === 'repassar';
    if (!nome || !endereco) { mostrarToast('Preencha os dados.', 'error'); return; }
    if (carrinho.length === 0) return;

    const btn = document.querySelector('#view-checkout .btn-order');
    btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processando...';

    const tipoVenda = carrinho.some(i => i.tipo === 'encomenda') ? 'encomenda' : 'pronta-entrega';
    const dataEntrega = carrinho.find(i => i.tipo === 'encomenda')?.dataObj || new Date().toISOString().split('T')[0];
    const horaEntrega = carrinho.find(i => i.tipo === 'encomenda')?.horaObj || '12:00';

    const itens = carrinho.map(it => ({
        produto_id: it.produtoId,
        variacao_nome: it.variacao,
        personalizacao: [it.textoPersonalizado, it.obs].filter(Boolean).join(' | ') || null,
    }));

    try {
        const resultado = await api('POST', '/pedidos/criar.php', {
            cliente_nome: nome, cliente_telefone: telefone, cliente_endereco: endereco,
            modalidade, tipo_venda: tipoVenda, data_entrega: dataEntrega,
            hora_entrega: horaEntrega + ':00', forma_pagamento: forma,
            repassa_taxa: repassa, alergias: alergias || null, observacoes: obs || null, itens
        });

        const valorTotal = carrinho.reduce((s, i) => s + i.preco * i.qtd, 0);
        const pedidoId = resultado?.id || resultado?.data?.id || null;

        carrinho = [];
        atualizarBadgeCarrinho();
        if (perfilAtivo === 'admin') await carregarDadosAdmin();
        else await carregarDadosCliente();

        switchView(clienteLogado ? 'cliente' : 'vitrine');

        if (forma === 'pix' && typeof abrirPopupPix === 'function') {
            setTimeout(() => abrirPopupPix(valorTotal, pedidoId), 300);
        } else {
            mostrarToast('Pedido confirmado com sucesso!');
            if (typeof adicionarNotificacao === 'function') {
                adicionarNotificacao('info', 'Pedido Confirmado', `Pedido ${pedidoId || ''} recebido com sucesso!`, null);
            }
        }
    } catch (e) {
        mostrarToast(e.message || 'Erro ao confirmar pedido.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Confirmar Pedido <i class="fa-solid fa-check"></i>';
    }
};

/* PEDIDOS (kanban) */
window.avancarStatusPedidoGeral = async function (idReq, novoStatus) {
    try {
        await api('PUT', '/pedidos/atualizar-status.php', { pedido_id: idReq, status: novoStatus });
        await carregarDadosAdmin();
        renderizarGestaoPedidos(); renderizarAreaCliente(); verificarAlertas();
        mostrarToast('Status atualizado!');
    } catch (e) { mostrarToast(e.message, 'error'); }
};

window.receberSaldoEDespacharGeral = async function (idReq) {
    const itens = dbVendas.filter(v => v.idReq === idReq);
    const saldo = itens.reduce((s, i) => s + i.saldoDevedor, 0);
    if (!confirm(`Confirmar recebimento de R$ ${saldo.toFixed(2).replace('.', ',')}?`)) return;
    try {
        await api('PUT', '/pedidos/atualizar-status.php', { pedido_id: idReq, receber_saldo: true, status: 'Entregue' });
        await carregarDadosAdmin();
        renderizarGestaoPedidos();
        mostrarToast('Saldo recebido!');
    } catch (e) { mostrarToast(e.message, 'error'); }
};

window.responderPedido = async function (idReq) {
    const p = dbVendas.find(v => v.idReq === idReq);
    const r = prompt('Mensagem ao cliente:', p?.respostaAdmin || '');
    if (r === null) return;
    try {
        await api('PUT', '/pedidos/atualizar-status.php', { pedido_id: idReq, resposta_admin: r, status: p.statusPedido });
        dbVendas.filter(v => v.idReq === idReq).forEach(i => i.respostaAdmin = r);
        renderizarGestaoPedidos();
        mostrarToast('Mensagem enviada!');
    } catch (e) { mostrarToast(e.message, 'error'); }
};

/* PRODUTOS (FICHAS TÉCNICAS) */
window.salvarReceita = async function () {
    const n = document.getElementById('rec-nome').value.trim();
    let cat = document.getElementById('rec-cat-select').value;
    if (cat === 'NOVA_CATEGORIA') cat = document.getElementById('rec-cat-nova').value.trim();
    let img = document.getElementById('rec-img').value.trim();
    const id = document.getElementById('rec-id').value;

    // Upload de imagem se houver arquivo selecionado
    const inputImg = document.getElementById('rec-img-upload');
    if (inputImg && inputImg.files && inputImg.files[0]) {
        try {
            const up = await apiUpload(inputImg.files[0]);
            img = up.url;
            mostrarToast('Imagem enviada!');
        } catch (e) { mostrarToast('Erro no upload: ' + e.message, 'error'); return; }
    }
    if (!img) img = 'https://images.unsplash.com/photo-1551024506-0bccd828d307?q=80&w=400';

    const ops = [], rec = [];
    document.querySelectorAll('#rec-opcoes-tabela tr').forEach(tr => {
        const nm = tr.querySelector('.rec-opt-nome')?.value.trim();
        const pr = parseFloat(tr.querySelector('.rec-opt-preco')?.value);
        const mt = parseFloat(tr.querySelector('.rec-opt-mult')?.value) || 1;
        if (nm && pr) ops.push({ nome: nm, preco: pr, mult: mt });
    });
    document.querySelectorAll('#rec-ingredientes-tabela tr').forEach(tr => {
        const ii = tr.querySelector('.rec-insumo-select')?.value;
        const qt = parseFloat(tr.querySelector('.rec-insumo-qtd')?.value);
        if (ii && qt) rec.push({ insumo_id: ii, quantidade: qt });
    });
    if (!n || ops.length === 0 || !cat) { alert('Nome, categoria e ao menos uma variação são obrigatórios.'); return; }

    const cats = await api('GET', '/categorias/index.php');
    let catId = cats.find(c => c.nome === cat)?.id;
    if (!catId) {
        const res = await api('POST', '/categorias/criar.php', { nome: cat });
        catId = res.id;
    }
    const body = {
        nome: n, categoria_id: catId,
        descricao: document.getElementById('rec-desc').value,
        nutricional: document.getElementById('rec-nutri').value,
        modo_preparo: document.getElementById('rec-preparo').value,
        imagem_url: img,
        grid_vitrine: document.getElementById('rec-grid').value,
        ordem_vitrine: parseInt(document.getElementById('rec-ordem').value) || 99,
        permite_encomenda: document.getElementById('rec-permite-encomenda').value,
        sinal_minimo_perc: parseInt(document.getElementById('rec-sinal-min').value) || 0,
        antecedencia_min_dias: parseInt(document.getElementById('rec-antecedencia').value) || 0,
        opcoes: ops, receita: rec,
    };
    try {
        if (id) await api('PUT', `/produtos/editar.php?id=${id}`, body);
        else await api('POST', '/produtos/criar.php', body);
        fecharModal('modal-receita');
        const produtos = await api('GET', '/produtos/index.php');
        dbProdutos = _mapProdutos(produtos);
        renderizarFiltrosVitrine();
        atualizarTelasOperacionais();
        filtrar(categoriaAtual);
        mostrarToast('Ficha salva!');
    } catch (e) { mostrarToast(e.message, 'error'); }
};

window.confirmarExclusaoReceita = async function () {
    const id = parseInt(document.getElementById('id-exclusao-pendente').value);
    try {
        await api('DELETE', `/produtos/deletar.php?id=${id}`);
        fecharModal('modal-confirmar-exclusao');
        dbProdutos = dbProdutos.filter(p => p.id !== id);
        normalizarOrdem(); atualizarTelasOperacionais(); renderizarFiltrosVitrine(); filtrar('Todos');
        mostrarToast('Produto excluído!');
    } catch (e) { mostrarToast(e.message, 'error'); }
};

window.toggleAtivarProduto = async function (id) {
    try {
        await api('POST', `/produtos/toggle-ativo.php?id=${id}`);
        const p = dbProdutos.find(x => x.id === id);
        if (p) p.ativo = !p.ativo;
        atualizarTelasOperacionais();
        filtrar(categoriaAtual);
        mostrarToast(dbProdutos.find(x => x.id === id)?.ativo ? 'Ativado!' : 'Ocultado.');
    } catch (e) { mostrarToast(e.message, 'error'); }
};

window.moverOrdem = async function (id, dir) {
    try {
        await api('POST', '/produtos/reordenar.php', { id, dir });
        const produtos = await api('GET', '/produtos/index.php');
        dbProdutos = _mapProdutos(produtos);
        atualizarTelasOperacionais();
        filtrar(categoriaAtual);
    } catch (e) { mostrarToast('Erro ao reordenar.', 'error'); }
};

/* CATEGORIAS */
window.adicionarCategoria = async function () {
    const nova = document.getElementById('nova-categoria').value.trim();
    if (!nova) return alert('Digite o nome da categoria.');
    try {
        await api('POST', '/categorias/criar.php', { nome: nova });
        document.getElementById('nova-categoria').value = '';
        const cats = await api('GET', '/categorias/index.php');
        dbCategorias = cats.map(c => c.nome);
        abrirModalCategorias();
        carregarOpcoesCategoria();
        renderizarFiltrosVitrine();
        mostrarToast('Categoria adicionada!');
    } catch (e) { mostrarToast(e.message, 'error'); }
};

window.excluirCategoriaGlobal = async function (nome) {
    const cats = await api('GET', '/categorias/index.php').catch(() => []);
    const cat = cats.find(c => c.nome === nome);
    if (!cat) return;
    if (dbProdutos.some(p => p.cat === nome) && !confirm(`Categoria '${nome}' está em uso. Continuar?`)) return;
    try {
        await api('DELETE', `/categorias/deletar.php?id=${cat.id}`);
        dbCategorias = dbCategorias.filter(x => x !== nome);
        renderizarFiltrosVitrine();
        atualizarTelasOperacionais();
        abrirModalCategorias();
        mostrarToast('Categoria excluída.');
    } catch (e) { mostrarToast(e.message, 'error'); }
};

/* ESTOQUE */
window.salvarLote = async function () {
    const ide = document.getElementById('form-insumo-existente').value;
    const qtd = parseFloat(document.getElementById('form-qtd').value);
    const custo = parseFloat(document.getElementById('form-custo').value);
    const di = document.getElementById('form-inclusao').value;
    const dv = document.getElementById('form-vencimento').value;
    if (isNaN(qtd) || qtd <= 0 || !dv || isNaN(custo)) return alert('Preencha todos os campos.');
    const body = { insumo_id: ide || null, quantidade: qtd, custo_unitario: custo, data_inclusao: di, data_vencimento: dv };
    if (!ide) {
        body.nome = document.getElementById('form-nome').value.trim();
        body.unidade = document.getElementById('form-unid').value;
    }
    try {
        await api('POST', '/estoque/lotes/entrada.php', body);
        fecharModal('modal-insumo');
        const e = await api('GET', '/estoque/index.php');
        dbEstoque = _mapEstoque(e);
        atualizarTelasOperacionais();
        filtrar(categoriaAtual);
        verificarAlertas();
        mostrarToast('Lote registrado!');
    } catch (e) { mostrarToast(e.message, 'error'); }
};

window.removerLote = async function (idIns, idLote) {
    try {
        await api('DELETE', `/estoque/lotes/remover.php?id=${idLote}`);
        const ins = dbEstoque.find(e => e.id === idIns);
        if (ins) ins.lotes = ins.lotes.filter(l => l.idLote !== idLote);
        abrirLotesInsumo(idIns);
        atualizarTelasOperacionais();
        filtrar(categoriaAtual);
        verificarAlertas();
    } catch (e) { mostrarToast(e.message, 'error'); }
};

window.registrarPerda = async function () {
    const idI = document.getElementById('perda-insumo').value;
    const idL = document.getElementById('perda-lote').value;
    const qtd = parseFloat(document.getElementById('perda-qtd').value);
    const mot = document.getElementById('perda-motivo').value;
    if (!idI || isNaN(qtd) || qtd <= 0) return alert('Preencha todos os campos.');
    try {
        await api('POST', '/estoque/baixa.php', { insumo_id: idI, lote_id: idL || null, quantidade: qtd, motivo: mot });
        fecharModal('modal-perda');
        const e = await api('GET', '/estoque/index.php');
        dbEstoque = _mapEstoque(e);
        atualizarTelasOperacionais();
        filtrar(categoriaAtual);
        verificarAlertas();
        mostrarToast('Baixa registrada.');
    } catch (e) { mostrarToast(e.message, 'error'); }
};

/* CONFIGURAÇÕES */
window.salvarConfiguracoes = async function () {
    if (typeof salvarInputsConfig === 'function') salvarInputsConfig();
    const body = {
        taxa_debito: document.getElementById('cfg-taxa-debito').value,
        taxa_credito: document.getElementById('cfg-taxa-credito').value,
        taxa_pix: document.getElementById('cfg-taxa-pix').value,
        prazo_recebimento_credito: document.getElementById('cfg-prazo-credito').value,
        politica_taxa: document.getElementById('cfg-politica-taxa').value,
        custos_fixos: dbConfig.custosFixos,
        regras_fidelidade: dbConfig.regrasFidelidade,
    };
    try {
        await api('POST', '/configuracoes/salvar.php', body);
        mostrarToast('Configurações salvas!');
        renderizarAreaCliente();
    } catch (e) { mostrarToast(e.message, 'error'); }
};

/* DATAS BLOQUEADAS (novo recurso v30_1) */
window.adicionarDataBloqueio = async function () {
    const input = document.getElementById('cfg-data-bloqueio');
    const data = input.value;
    if (!data) { mostrarToast('Selecione uma data.', 'warning'); return; }
    try {
        await api('POST', '/configuracoes/bloquear-data.php', { data, motivo: null });
        if (!dbConfig.datasBloqueadas) dbConfig.datasBloqueadas = [];
        if (!dbConfig.datasBloqueadas.includes(data)) dbConfig.datasBloqueadas.push(data);
        input.value = '';
        renderDatasBloqueadas();
        mostrarToast('Data bloqueada!');
    } catch (e) { mostrarToast(e.message, 'error'); }
};

window.removerDataBloqueio = async function (data) {
    try {
        await api('DELETE', `/configuracoes/desbloquear-data.php?data=${data}`);
        dbConfig.datasBloqueadas = (dbConfig.datasBloqueadas || []).filter(d => d !== data);
        renderDatasBloqueadas();
        mostrarToast('Data desbloqueada!');
    } catch (e) { mostrarToast(e.message, 'error'); }
};

/* ============================================================
   INICIALIZAÇÃO (substitui o window.load do HTML)
   ============================================================ */
window.addEventListener('load', async () => {
    try {
        // 1. Carrega vitrine pública
        await carregarVitrine();

        // 2. Limpa qualquer sessão salva — sistema sempre inicia sem login
        TOKEN = null;
        localStorage.removeItem('dg_jwt');

        // 3. Render padrão
        if (typeof popularFiltrosDinamicos === 'function') popularFiltrosDinamicos();
        renderizarFiltrosVitrine();
        if (typeof atualizarTelasOperacionais === 'function') atualizarTelasOperacionais();
        if (typeof renderizarGestaoPedidos === 'function') renderizarGestaoPedidos();
        filtrar('Todos');
        history.replaceState({ view: 'vitrine' }, '', '#vitrine');
        switchView('vitrine', false);
    } catch (error) {
        console.error('Inicialização:', error);
        if (typeof mostrarToast === 'function') mostrarToast('Erro: ' + error.message, 'error');
    }
});
