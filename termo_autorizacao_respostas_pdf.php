<?php
/**
 * PDF das autorizações do termo (gerado no servidor — sem cabeçalho/rodapé do navegador).
 */
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/lib/fpdf/fpdf.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$termoModel = new TermoAutorizacao($db);

$pode_ver = $user->isAdmin() || $user->isNivel0() || $user->isNivel1() || $user->isNivel2();
if (!$user->isLoggedIn() || !$pode_ver) {
    header('Location: index.php');
    exit;
}

$termo_id = (int) ($_GET['id'] ?? 0);
$termo = $termo_id ? $termoModel->getById($termo_id) : null;
if (!$termo) {
    $_SESSION['error'] = 'Autorização não encontrada.';
    header('Location: termos_autorizacao.php');
    exit;
}

$listas = $termoModel->montarListasDecisoesParaPdf($termo_id);
$turmas_label = [];
foreach ($termo['turmas'] ?? [] as $tu) {
    $turmas_label[] = ($tu['curso_nome'] ?? '') . ' - ' . (int) ($tu['ano_curso'] ?? 0) . 'º';
}

$fmtData = static function ($valor) {
    if (empty($valor)) {
        return '—';
    }
    $ts = strtotime($valor);
    return $ts ? date('d/m/Y H:i', $ts) : '—';
};

$fmtResponsavel = static function (array $linha) {
    $nome = trim((string) ($linha['responsavel_nome'] ?? '—'));
    $parentesco = trim((string) ($linha['parentesco'] ?? ''));
    if ($parentesco !== '' && $nome !== '—') {
        return $nome . ' (' . $parentesco . ')';
    }
    return $nome !== '' ? $nome : '—';
};

$pdfTxt = static function ($texto) {
    $texto = (string) $texto;
    if ($texto === '') {
        return '';
    }
    if (function_exists('iconv')) {
        $convertido = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto);
        if ($convertido !== false) {
            return $convertido;
        }
    }
    return utf8_decode($texto);
};

$secoes = [
    ['titulo' => 'Discordaram', 'itens' => $listas['discordaram']],
    ['titulo' => 'Concordaram', 'itens' => $listas['concordaram']],
    ['titulo' => 'Não responderam', 'itens' => $listas['nao_responderam']],
];

class TermoAutorizacoesPdf extends FPDF
{
    public function Header()
    {
        // Sem cabeçalho (evita repetir título/data em todas as páginas).
    }

    public function Footer()
    {
        // Sem rodapé com URL; apenas número da página, discreto.
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 8, (string) $this->PageNo() . '/{nb}', 0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }
}

$pdf = new TermoAutorizacoesPdf('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 18);
$pdf->AddPage();

$titulo = 'Autorizações — ' . ($termo['titulo'] ?? '');
$pdf->SetFont('Arial', 'B', 14);
$pdf->MultiCell(0, 7, $pdfTxt($titulo), 0, 'L');
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 10);
$data_evento = date('d/m/Y', strtotime($termo['data_evento']));
if (!empty($termo['hora'])) {
    $data_evento .= ' às ' . substr($termo['hora'], 0, 5);
}
$pdf->Cell(0, 5, $pdfTxt('Data do evento: ' . $data_evento), 0, 1);
$pdf->Cell(0, 5, $pdfTxt('Turmas: ' . (!empty($turmas_label) ? implode(', ', $turmas_label) : '—')), 0, 1);
$pdf->Cell(0, 5, $pdfTxt('Gerado em: ' . date('d/m/Y H:i')), 0, 1);
$pdf->Ln(4);

$wNum = 12;
$wAluno = 55;
$wResp = 75;
$wData = 38;
$wTotal = $wNum + $wAluno + $wResp + $wData;

$imprimirCabecalhoTabela = static function ($pdf, $pdfTxt, $wNum, $wAluno, $wResp, $wData) {
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell($wNum, 6, $pdfTxt('Nº'), 1, 0, 'R', true);
    $pdf->Cell($wAluno, 6, $pdfTxt('Aluno'), 1, 0, 'L', true);
    $pdf->Cell($wResp, 6, $pdfTxt('Responsável'), 1, 0, 'L', true);
    $pdf->Cell($wData, 6, $pdfTxt('Data'), 1, 1, 'L', true);
    $pdf->SetFont('Arial', '', 9);
};

foreach ($secoes as $secao) {
    if ($pdf->GetY() > 250) {
        $pdf->AddPage();
    }

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 7, $pdfTxt($secao['titulo'] . ' (' . count($secao['itens']) . ')'), 0, 1);
    $pdf->Ln(1);

    if (empty($secao['itens'])) {
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->Cell(0, 5, $pdfTxt('Nenhum registro nesta lista.'), 0, 1);
        $pdf->Ln(4);
        continue;
    }

    $turma_atual = null;
    $n = 0;
    foreach ($secao['itens'] as $linha) {
        $turma = (string) ($linha['turma_label'] ?? '—');
        if ($turma !== $turma_atual) {
            if ($pdf->GetY() > 255) {
                $pdf->AddPage();
            }
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(220, 230, 240);
            $pdf->Cell($wTotal, 6, $pdfTxt($turma), 1, 1, 'L', true);
            $imprimirCabecalhoTabela($pdf, $pdfTxt, $wNum, $wAluno, $wResp, $wData);
            $turma_atual = $turma;
        }

        $n++;
        if ($pdf->GetY() > 270) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(220, 230, 240);
            $pdf->Cell($wTotal, 6, $pdfTxt($turma), 1, 1, 'L', true);
            $imprimirCabecalhoTabela($pdf, $pdfTxt, $wNum, $wAluno, $wResp, $wData);
        }

        $pdf->Cell($wNum, 6, (string) $n, 1, 0, 'R');
        $pdf->Cell($wAluno, 6, $pdfTxt($linha['aluno_nome'] ?? ''), 1, 0, 'L');
        $pdf->Cell($wResp, 6, $pdfTxt($fmtResponsavel($linha)), 1, 0, 'L');
        $pdf->Cell($wData, 6, $pdfTxt($fmtData($linha['decidido_em'] ?? null)), 1, 1, 'L');
    }
    $pdf->Ln(5);
}

$nome_arquivo = 'autorizacoes_' . $termo_id . '_' . date('Ymd_His') . '.pdf';
$pdf->Output('I', $nome_arquivo);
exit;
