<?php
require_once 'vendor/autoload.php';

class InvoicePDF extends TCPDF {
    private $invoice;
    private $client;
    private $company;
    
    public function __construct($invoice, $client, $company) {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8');
        $this->invoice = $invoice;
        $this->client = $client;
        $this->company = $company;
        
        // Configuración del documento
        $this->SetCreator($company['name']);
        $this->SetAuthor($company['name']);
        $this->SetTitle('Factura ' . $invoice['invoice_number']);
        
        // Configuración de márgenes
        $this->SetMargins(15, 15, 15);
        $this->SetAutoPageBreak(true, 15);
        
        // Configuración de fuente
        $this->SetFont('helvetica', '', 10);
    }
    
    public function Header() {
        // Logo
        $this->Image('assets/images/logo.png', 15, 10, 30);
        
        // Información de la empresa
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, $this->company['name'], 0, 1, 'R');
        $this->SetFont('helvetica', '', 9);
        $this->Cell(0, 5, 'RFC: ' . $this->company['rfc'], 0, 1, 'R');
        $this->Cell(0, 5, $this->company['address'], 0, 1, 'R');
        $this->Cell(0, 5, $this->company['phone'] . ' - ' . $this->company['email'], 0, 1, 'R');
        
        // Número y estado de factura
        $this->Ln(10);
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, 'FACTURA ' . $this->invoice['invoice_number'], 0, 1, 'C');
        
        // Estado de la factura
        $status = $this->invoice['status'] === 'paid' ? 'PAGADA' : 
                 ($this->invoice['status'] === 'pending' ? 'PENDIENTE' : 'VENCIDA');
        $this->SetFont('helvetica', 'B', 12);
        $this->SetTextColor($this->invoice['status'] === 'paid' ? 0 : 200, 0, 0);
        $this->Cell(0, 10, $status, 0, 1, 'C');
        $this->SetTextColor(0);
        
        $this->Ln(5);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . 
                          $this->getAliasNbPages(), 0, 0, 'C');
    }
    
    public function generateInvoice() {
        $this->AddPage();
        
        // Información del cliente
        $this->SetFont('helvetica', 'B', 11);
        $this->Cell(0, 10, 'DATOS DEL CLIENTE', 0, 1, 'L');
        
        $this->SetFont('helvetica', '', 10);
        $this->Cell(40, 7, 'Razón Social:', 0);
        $this->Cell(0, 7, $this->client['business_name'], 0, 1);
        
        $this->Cell(40, 7, 'RFC:', 0);
        $this->Cell(0, 7, $this->client['rfc'], 0, 1);
        
        $this->Cell(40, 7, 'Régimen Fiscal:', 0);
        $this->Cell(0, 7, $this->client['tax_regime'], 0, 1);
        
        $this->Cell(40, 7, 'Dirección:', 0);
        $address = $this->client['street'] . ' ' . $this->client['ext_number'];
        if ($this->client['int_number']) {
            $address .= ' Int. ' . $this->client['int_number'];
        }
        $address .= ', ' . $this->client['neighborhood'] . "\n" .
                   $this->client['city'] . ', ' . $this->client['state'] . ' CP ' . $this->client['zip_code'];
        $this->MultiCell(0, 7, $address, 0);
        
        // Información de la factura
        $this->Ln(5);
        $this->SetFont('helvetica', 'B', 11);
        $this->Cell(0, 10, 'DATOS DE LA FACTURA', 0, 1, 'L');
        
        $this->SetFont('helvetica', '', 10);
        $this->Cell(40, 7, 'Fecha Emisión:', 0);
        $this->Cell(60, 7, date('d/m/Y', strtotime($this->invoice['issue_date'])), 0);
        $this->Cell(40, 7, 'Fecha Vencimiento:', 0);
        $this->Cell(0, 7, date('d/m/Y', strtotime($this->invoice['due_date'])), 0, 1);
        
        // Conceptos
        $this->Ln(5);
        $this->SetFont('helvetica', 'B', 11);
        $this->Cell(0, 10, 'CONCEPTOS', 0, 1, 'L');
        
        // Encabezados de la tabla
        $this->SetFillColor(240, 240, 240);
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(20, 7, 'Cant.', 1, 0, 'C', true);
        $this->Cell(30, 7, 'Clave', 1, 0, 'C', true);
        $this->Cell(80, 7, 'Descripción', 1, 0, 'C', true);
        $this->Cell(30, 7, 'P. Unitario', 1, 0, 'C', true);
        $this->Cell(30, 7, 'Importe', 1, 1, 'C', true);
        
        // Detalles
        $this->SetFont('helvetica', '', 10);
        foreach ($this->invoice['items'] as $item) {
            $this->Cell(20, 7, number_format($item['quantity'], 2), 1, 0, 'C');
            $this->Cell(30, 7, $item['product_key'], 1, 0, 'C');
            $this->Cell(80, 7, $item['description'], 1, 0, 'L');
            $this->Cell(30, 7, '$' . number_format($item['unit_price'], 2), 1, 0, 'R');
            $this->Cell(30, 7, '$' . number_format($item['quantity'] * $item['unit_price'], 2), 1, 1, 'R');
        }
        
        // Totales
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(160, 7, 'Subtotal:', 1, 0, 'R');
        $this->Cell(30, 7, '$' . number_format($this->invoice['subtotal'], 2), 1, 1, 'R');
        
        $this->Cell(160, 7, 'IVA (16%):', 1, 0, 'R');
        $this->Cell(30, 7, '$' . number_format($this->invoice['tax'], 2), 1, 1, 'R');
        
        $this->Cell(160, 7, 'Total:', 1, 0, 'R');
        $this->Cell(30, 7, '$' . number_format($this->invoice['total_amount'], 2), 1, 1, 'R');
        
        // Si hay pagos, mostrar historial
        if (!empty($this->invoice['payments'])) {
            $this->AddPage();
            $this->SetFont('helvetica', 'B', 11);
            $this->Cell(0, 10, 'HISTORIAL DE PAGOS', 0, 1, 'L');
            
            // Encabezados
            $this->SetFillColor(240, 240, 240);
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(30, 7, 'Fecha', 1, 0, 'C', true);
            $this->Cell(40, 7, 'Método', 1, 0, 'C', true);
            $this->Cell(50, 7, 'Referencia', 1, 0, 'C', true);
            $this->Cell(30, 7, 'Monto', 1, 0, 'C', true);
            $this->Cell(40, 7, 'Registrado por', 1, 1, 'C', true);
            
            // Detalles de pagos
            $this->SetFont('helvetica', '', 10);
            foreach ($this->invoice['payments'] as $payment) {
                $this->Cell(30, 7, date('d/m/Y', strtotime($payment['payment_date'])), 1, 0, 'C');
                $method = $payment['payment_method'] === 'transfer' ? 'Transferencia' : 
                         ($payment['payment_method'] === 'cash' ? 'Efectivo' : 
                          ($payment['payment_method'] === 'check' ? 'Cheque' : 'Tarjeta'));
                $this->Cell(40, 7, $method, 1, 0, 'C');
                $this->Cell(50, 7, $payment['reference'], 1, 0, 'L');
                $this->Cell(30, 7, '$' . number_format($payment['amount'], 2), 1, 0, 'R');
                $this->Cell(40, 7, $payment['registered_by_name'], 1, 1, 'C');
            }
        }
    }
} 