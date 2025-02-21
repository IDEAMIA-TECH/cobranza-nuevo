<?php
require_once 'vendor/autoload.php';

class ReportPDF extends TCPDF {
    private $title;
    private $period;
    private $filters;
    
    public function __construct($title, $period, $filters = []) {
        parent::__construct('L', 'mm', 'A4', true, 'UTF-8');
        $this->title = $title;
        $this->period = $period;
        $this->filters = $filters;
        
        // Configuración del documento
        $this->SetCreator('Sistema de Cobranza');
        $this->SetAuthor('Sistema de Cobranza');
        $this->SetTitle($title);
        
        // Configuración de márgenes
        $this->SetMargins(15, 15, 15);
        $this->SetAutoPageBreak(true, 15);
        
        // Configuración de fuente
        $this->SetFont('helvetica', '', 10);
    }
    
    public function Header() {
        // Logo
        $this->Image('assets/images/logo.png', 15, 10, 30);
        
        // Título del reporte
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, $this->title, 0, 1, 'C');
        
        // Período
        $this->SetFont('helvetica', '', 11);
        $this->Cell(0, 8, 'Período: ' . $this->period, 0, 1, 'C');
        
        // Filtros aplicados
        if (!empty($this->filters)) {
            $this->SetFont('helvetica', 'I', 9);
            $filters_text = 'Filtros: ' . implode(' | ', $this->filters);
            $this->Cell(0, 6, $filters_text, 0, 1, 'C');
        }
        
        $this->Ln(5);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . 
                          $this->getAliasNbPages(), 0, 0, 'C');
    }
    
    public function addSummaryTable($data) {
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, 'Resumen General', 0, 1, 'L');
        
        $this->SetFillColor(245, 246, 250);
        $this->SetFont('helvetica', '', 10);
        
        $w = array(60, 45, 45, 45, 45);
        
        // Encabezados
        $this->Cell($w[0], 7, 'Concepto', 1, 0, 'C', true);
        $this->Cell($w[1], 7, 'Total', 1, 0, 'C', true);
        $this->Cell($w[2], 7, 'Pagado', 1, 0, 'C', true);
        $this->Cell($w[3], 7, 'Pendiente', 1, 0, 'C', true);
        $this->Cell($w[4], 7, 'Vencido', 1, 1, 'C', true);
        
        // Datos
        $this->Cell($w[0], 7, 'Facturas', 1, 0, 'L');
        $this->Cell($w[1], 7, $data['total_invoices'], 1, 0, 'C');
        $this->Cell($w[2], 7, $data['paid_invoices'], 1, 0, 'C');
        $this->Cell($w[3], 7, $data['pending_invoices'], 1, 0, 'C');
        $this->Cell($w[4], 7, $data['overdue_invoices'], 1, 1, 'C');
        
        $this->Cell($w[0], 7, 'Montos', 1, 0, 'L');
        $this->Cell($w[1], 7, '$' . number_format($data['total_amount'], 2), 1, 0, 'C');
        $this->Cell($w[2], 7, '$' . number_format($data['collected_amount'], 2), 1, 0, 'C');
        $this->Cell($w[3], 7, '$' . number_format($data['pending_amount'], 2), 1, 0, 'C');
        $this->Cell($w[4], 7, '$' . number_format($data['overdue_amount'], 2), 1, 1, 'C');
        
        $this->Ln(10);
    }
    
    public function addPaymentMethodsTable($data) {
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, 'Pagos por Método', 0, 1, 'L');
        
        $this->SetFillColor(245, 246, 250);
        $this->SetFont('helvetica', '', 10);
        
        $w = array(60, 45, 45);
        
        // Encabezados
        $this->Cell($w[0], 7, 'Método', 1, 0, 'C', true);
        $this->Cell($w[1], 7, 'Cantidad', 1, 0, 'C', true);
        $this->Cell($w[2], 7, 'Monto Total', 1, 1, 'C', true);
        
        foreach ($data as $row) {
            $method = $row['payment_method'] === 'transfer' ? 'Transferencia' : 
                     ($row['payment_method'] === 'cash' ? 'Efectivo' : 
                      ($row['payment_method'] === 'check' ? 'Cheque' : 'Tarjeta'));
            
            $this->Cell($w[0], 7, $method, 1, 0, 'L');
            $this->Cell($w[1], 7, $row['total_payments'], 1, 0, 'C');
            $this->Cell($w[2], 7, '$' . number_format($row['total_amount'], 2), 1, 1, 'C');
        }
        
        $this->Ln(10);
    }
    
    public function addAgingTable($data) {
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, 'Antigüedad de Saldos', 0, 1, 'L');
        
        $this->SetFillColor(245, 246, 250);
        $this->SetFont('helvetica', '', 10);
        
        $w = array(60, 45, 45);
        
        // Encabezados
        $this->Cell($w[0], 7, 'Antigüedad', 1, 0, 'C', true);
        $this->Cell($w[1], 7, 'Facturas', 1, 0, 'C', true);
        $this->Cell($w[2], 7, 'Monto', 1, 1, 'C', true);
        
        foreach ($data as $row) {
            $this->Cell($w[0], 7, $row['aging'], 1, 0, 'L');
            $this->Cell($w[1], 7, $row['total_invoices'], 1, 0, 'C');
            $this->Cell($w[2], 7, '$' . number_format($row['total_amount'], 2), 1, 1, 'C');
        }
        
        $this->Ln(10);
    }
} 