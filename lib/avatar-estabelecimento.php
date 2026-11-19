<?php
declare(strict_types=1);

/**
 * Avatar público do estabelecimento autenticado, sem coluna adicional no banco.
 * Nunca recebe ID nem caminho do navegador: os chamadores usam a sessão validada.
 */
function agenday_avatar_diretorio(): string
{
    return dirname(__DIR__) . '/arquivos/estabelecimentos';
}

function agenday_avatar_caminho(int $id, string $extensao): string
{
    if ($id < 1 || !in_array($extensao, ['jpg', 'png'], true)) {
        throw new InvalidArgumentException('Identificador ou extensão inválidos.');
    }
    return agenday_avatar_diretorio() . '/avatar-' . $id . '.' . $extensao;
}

/** Retorna uma URL relativa apenas se existir um arquivo seguro neste estabelecimento. */
function agenday_avatar_url(int $id): ?string
{
    if ($id < 1) {
        return null;
    }
    $encontrados = [];
    foreach (['jpg', 'png'] as $extensao) {
        $arquivo = agenday_avatar_caminho($id, $extensao);
        if (is_file($arquivo) && !is_link($arquivo)) {
            $encontrados[] = [$extensao, (int) filemtime($arquivo)];
        }
    }
    if ($encontrados === []) {
        return null;
    }
    usort($encontrados, static fn(array $a, array $b): int => $b[1] <=> $a[1]);
    [$extensao] = $encontrados[0];
    // Hash do conteúdo evita avatar antigo em cache em duas trocas no mesmo segundo.
    $hash = hash_file('sha256', agenday_avatar_caminho($id, $extensao));
    if (!is_string($hash)) {
        return null;
    }
    return 'arquivos/estabelecimentos/avatar-' . $id . '.' . $extensao
        . '?v=' . substr($hash, 0, 16);
}

/** Valida tamanho, dimensões, tipo real e MIME. Rejeita SVG e formatos executáveis. */
function agenday_avatar_validar_imagem(string $arquivo, int $bytes): string
{
    if ($bytes < 1 || $bytes > 2 * 1024 * 1024 || !is_file($arquivo)) {
        throw new DomainException('A imagem deve ter até 2 MB e estar em JPG ou PNG.');
    }
    $dimensoes = @getimagesize($arquivo);
    if (!is_array($dimensoes) || $dimensoes[0] < 1 || $dimensoes[1] < 1
        || $dimensoes[0] > 4096 || $dimensoes[1] > 4096) {
        throw new DomainException('A imagem é inválida ou possui dimensões muito grandes.');
    }

    if ($dimensoes[2] === IMAGETYPE_JPEG) {
        $extensao = 'jpg';
        $mime = 'image/jpeg';
    } elseif ($dimensoes[2] === IMAGETYPE_PNG) {
        $extensao = 'png';
        $mime = 'image/png';
    } else {
        throw new DomainException('Envie apenas imagens JPG ou PNG.');
    }

    if (function_exists('finfo_open')) {
        $info = new finfo(FILEINFO_MIME_TYPE);
        if ($info->file($arquivo) !== $mime) {
            throw new DomainException('O formato real da imagem não corresponde ao arquivo.');
        }
    }
    return $extensao;
}

/** Salva atomicamente a foto, preservando a antiga quando a validação falhar. */
function agenday_avatar_salvar(int $id, mixed $upload): void
{
    if ($id < 1 || !is_array($upload)
        || !isset($upload['error']) || is_array($upload['error'])
        || $upload['error'] !== UPLOAD_ERR_OK
        || !isset($upload['tmp_name'], $upload['size'])
        || !is_string($upload['tmp_name']) || !is_int($upload['size'])
        || !is_uploaded_file($upload['tmp_name'])) {
        throw new DomainException('Selecione uma imagem JPG ou PNG de até 2 MB.');
    }

    $extensao = agenday_avatar_validar_imagem($upload['tmp_name'], $upload['size']);
    $diretorio = agenday_avatar_diretorio();
    // A regra anti-execução deve ter sido publicada ANTES de aceitar qualquer imagem.
    if (!is_dir($diretorio) || is_link($diretorio)
        || !is_file($diretorio . '/.htaccess')) {
        throw new RuntimeException('O armazenamento da foto não está disponível.');
    }

    $temporario = $diretorio . '/.upload-' . bin2hex(random_bytes(16)) . '.tmp';
    $destino = agenday_avatar_caminho($id, $extensao);
    if (!move_uploaded_file($upload['tmp_name'], $temporario)) {
        throw new RuntimeException('Não foi possível receber a imagem.');
    }

    try {
        if (!rename($temporario, $destino)) {
            throw new RuntimeException('Não foi possível atualizar a imagem.');
        }
        foreach (['jpg', 'png'] as $outra) {
            if ($outra !== $extensao) {
                $anterior = agenday_avatar_caminho($id, $outra);
                if (is_file($anterior) && !is_link($anterior)) {
                    @unlink($anterior);
                }
            }
        }
        clearstatcache(true, $destino);
    } finally {
        if (is_file($temporario)) {
            @unlink($temporario);
        }
    }
}
