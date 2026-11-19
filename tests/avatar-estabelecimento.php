<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/avatar-estabelecimento.php';

function verificar(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException('FALHA: ' . $mensagem);
    }
}

$png1x1 = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/C6cAAAAASUVORK5CYII=',
    true
);
verificar(is_string($png1x1), 'fixture PNG indisponível.');
$arquivo = tempnam(sys_get_temp_dir(), 'ag-avatar-test-');
verificar(is_string($arquivo) && file_put_contents($arquivo, $png1x1) === strlen($png1x1),
    'não conseguiu criar fixture temporária.');

try {
    verificar(agenday_avatar_validar_imagem($arquivo, strlen($png1x1)) === 'png',
        'PNG real de 1x1 pixel deveria ser aceito.');
    verificar(agenday_avatar_url(0) === null,
        'ID de estabelecimento zero não pode expor avatar.');
    verificar(agenday_avatar_caminho(3, 'jpg') !== agenday_avatar_caminho(4, 'jpg'),
        'caminho do avatar deve estar isolado pelo ID.');

    $invalidos = [
        ['id' => 'arquivo vazio', 'conteudo' => '', 'bytes' => 0],
        ['id' => 'arquivo malicioso', 'conteudo' => '<?php echo 123;', 'bytes' => 15],
        ['id' => 'tamanho excedido', 'conteudo' => $png1x1, 'bytes' => 2 * 1024 * 1024 + 1],
    ];

    foreach ($invalidos as $caso) {
        file_put_contents($arquivo, $caso['conteudo']);
        $recusado = false;
        try {
            agenday_avatar_validar_imagem($arquivo, $caso['bytes']);
        } catch (DomainException $e) {
            $recusado = true;
        }
        verificar($recusado, $caso['id'] . ' deveria ser recusado.');
    }

    $recusado = false;
    try {
        agenday_avatar_caminho(1, 'php');
    } catch (InvalidArgumentException $e) {
        $recusado = true;
    }
    verificar($recusado, 'extensão PHP jamais pode formar um caminho.');

    $recusado = false;
    try {
        agenday_avatar_salvar(1, [
            'error' => UPLOAD_ERR_OK,
            'tmp_name' => $arquivo,
            'size' => 16,
        ]);
    } catch (DomainException $e) {
        $recusado = true;
    }
    verificar($recusado, 'arquivo não enviado por HTTP não deve ser aceito.');
    echo "OK: validação de avatar, isolamento por ID e upload inválido recusado.\n";
} finally {
    @unlink($arquivo);
}
