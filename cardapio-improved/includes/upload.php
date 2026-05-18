<?php
/**
 * Upload seguro de imagens
 * Valida tipo real (magic bytes), extensão, tamanho e sanitiza o nome
 */

/**
 * Processa e valida upload de imagem
 * @param  array  $file   $_FILES['campo']
 * @param  string $destDir Diretório de destino (com barra final)
 * @param  int    $maxKB  Tamanho máximo em KB (padrão 2048 = 2MB)
 * @return array  ['ok'=>bool, 'path'=>string|null, 'erro'=>string|null]
 */
function upload_imagem(array $file, string $destDir = '', int $maxKB = 2048): array
{
    if (empty($destDir)) {
        $destDir = __DIR__ . '/../assets/uploads/';
    }

    // Erros de upload do PHP
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msgs = [
            UPLOAD_ERR_INI_SIZE   => 'Arquivo muito grande (limite do servidor).',
            UPLOAD_ERR_FORM_SIZE  => 'Arquivo muito grande.',
            UPLOAD_ERR_PARTIAL    => 'Upload incompleto.',
            UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo enviado.',
        ];
        return ['ok' => false, 'path' => null, 'erro' => $msgs[$file['error']] ?? 'Erro de upload.'];
    }

    // Tamanho
    if ($file['size'] > $maxKB * 1024) {
        return ['ok' => false, 'path' => null, 'erro' => "Imagem muito grande. Máximo: {$maxKB}KB."];
    }

    // Verificar tipo real via magic bytes (não confiar no MIME informado pelo browser)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $tipoReal = $finfo->file($file['tmp_name']);

    $tiposPermitidos = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    if (!array_key_exists($tipoReal, $tiposPermitidos)) {
        return ['ok' => false, 'path' => null, 'erro' => 'Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WebP.'];
    }

    // Extensão correta baseada no tipo real
    $ext = $tiposPermitidos[$tipoReal];

    // Nome único e seguro
    $nomeArquivo = 'img_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destPath = rtrim($destDir, '/') . '/' . $nomeArquivo;

    // Criar diretório se não existir
    if (!is_dir(dirname($destPath))) {
        mkdir(dirname($destPath), 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['ok' => false, 'path' => null, 'erro' => 'Falha ao salvar arquivo.'];
    }

    // Caminho relativo para salvar no banco
    $caminhoRelativo = 'assets/uploads/' . $nomeArquivo;

    return ['ok' => true, 'path' => $caminhoRelativo, 'erro' => null];
}
