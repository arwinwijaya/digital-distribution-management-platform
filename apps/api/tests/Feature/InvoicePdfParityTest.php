<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Models\Outlet;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InvoicePdfParityTest extends TestCase
{
    use RefreshDatabase;

    protected User $outletUser;
    protected Outlet $outlet;
    protected string $outletToken;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\InvoiceTemplateSeeder::class);

        $this->outletUser = User::factory()->outlet()->create([
            'email' => 'pdf-parity-outlet@example.test',
            'password' => Hash::make('password123'),
        ]);
        $this->outlet = Outlet::factory()->create([
            'user_id' => $this->outletUser->id,
            'is_active' => true,
            'name' => 'TOKO MAJU',
            'phone' => '081234567890',
            'address' => 'Jl. Raya No. 1',
            'city' => 'Jakarta',
        ]);
        $this->outletToken = $this->login($this->outletUser);
        $admin = User::factory()->admin()->create([
            'email' => 'pdf-parity-admin@example.test',
            'password' => Hash::make('password123'),
        ]);
        $this->adminToken = $this->login($admin);
    }

    public function test_pdf_respects_template_show_npwp_false(): void
    {
        // Update template to hide NPWP
        $template = InvoiceTemplate::firstOrFail();
        $template->update(['show_npwp' => false, 'npwp' => '01.234.567.8-901.000']);

        $invoiceId = $this->createInvoiceForOutlet($this->outlet);
        $response = $this->withToken($this->outletToken)
            ->get("/api/invoices/{$invoiceId}/pdf");

        $response->assertOk();
        $pdfBytes = $response->getContent();

        // NPWP should NOT appear in PDF when show_npwp=false
        $this->assertStringNotContainsString('NPWP', $pdfBytes);
        $this->assertStringNotContainsString('01.234.567.8-901.000', $pdfBytes);

        // But company_name and address SHOULD appear
        $this->assertStringContainsString('PT Digital Distribusi Nusantara', $pdfBytes);
        $this->assertStringContainsString('Jl. Jend. Sudirman', $pdfBytes);
    }

    public function test_pdf_respects_template_show_outlet_phone_false(): void
    {
        $template = InvoiceTemplate::firstOrFail();
        $template->update(['show_outlet_phone' => false]);

        $invoiceId = $this->createInvoiceForOutlet($this->outlet);
        $response = $this->withToken($this->outletToken)
            ->get("/api/invoices/{$invoiceId}/pdf");

        $pdfBytes = $response->getContent();

        // Outlet phone should NOT appear when show_outlet_phone=false
        $this->assertStringNotContainsString('081234567890', $pdfBytes);
    }

    public function test_pdf_contains_primary_color_from_template(): void
    {
        $template = InvoiceTemplate::firstOrFail();
        $template->update(['primary_color' => '#FF5722']); // distinctive orange

        $invoiceId = $this->createInvoiceForOutlet($this->outlet);
        $response = $this->withToken($this->outletToken)
            ->get("/api/invoices/{$invoiceId}/pdf");

        $pdfBytes = $response->getContent();

        // primary_color is used in CSS, may not appear literally in PDF text.
        // Test via rendered HTML (Dompdf text extraction is unreliable for CSS colors).
        // The service provides renderHtml for this purpose in tests.
        $html = app(\App\Services\PdfGeneratorService::class)->renderHtml(
            Invoice::findOrFail($invoiceId),
            app(\App\Services\InvoiceService::class)->getDetail(Invoice::findOrFail($invoiceId)),
            $template,
        );
        $this->assertStringContainsString('#FF5722', $html);
    }

    public function test_pdf_contains_footer_text_and_notes_and_signer(): void
    {
        $template = InvoiceTemplate::firstOrFail();
        $template->update([
            'footer_text' => 'Custom Footer Text',
            'notes' => 'Custom Notes Here',
            'signer_name' => 'John Signer',
            'signer_title' => 'Finance Lead',
        ]);

        $invoiceId = $this->createInvoiceForOutlet($this->outlet);
        $response = $this->withToken($this->outletToken)
            ->get("/api/invoices/{$invoiceId}/pdf");

        $pdfBytes = $response->getContent();

        $this->assertStringContainsString('Custom Footer Text', $pdfBytes);
        $this->assertStringContainsString('Custom Notes Here', $pdfBytes);
        $this->assertStringContainsString('John Signer', $pdfBytes);
        $this->assertStringContainsString('Finance Lead', $pdfBytes);
    }

    public function test_cancelled_invoice_pdf_contains_batal_watermark(): void
    {
        $invoiceId = $this->createInvoiceForOutlet($this->outlet);
        $invoice = Invoice::findOrFail($invoiceId);
        $invoice->update(['status' => Invoice::CANCELLED]);

        $response = $this->withToken($this->outletToken)
            ->get("/api/invoices/{$invoiceId}/pdf");

        $response->assertOk();
        $pdfBytes = $response->getContent();

        // BATAL watermark should be present
        $this->assertStringContainsString('BATAL', $pdfBytes);
    }

    public function test_overdue_invoice_pdf_contains_overdue_badge(): void
    {
        $invoiceId = $this->createInvoiceForOutlet($this->outlet);
        $invoice = Invoice::findOrFail($invoiceId);
        $invoice->update([
            'due_date' => Carbon::yesterday()->toDateString(),
            'balance_amount' => 50000,
        ]);

        $response = $this->withToken($this->outletToken)
            ->get("/api/invoices/{$invoiceId}/pdf");

        $response->assertOk();
        $pdfBytes = $response->getContent();

        // OVERDUE badge should be present
        $this->assertStringContainsString('OVERDUE', $pdfBytes);
    }

    public function test_pdf_headers_content_type_and_filename(): void
    {
        $invoiceId = $this->createInvoiceForOutlet($this->outlet);
        $invoice = Invoice::findOrFail($invoiceId);
        $datePart = $invoice->issue_date instanceof Carbon
            ? $invoice->issue_date->format('Ymd')
            : Carbon::parse($invoice->issue_date)->format('Ymd');

        $response = $this->withToken($this->outletToken)
            ->get("/api/invoices/{$invoiceId}/pdf");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $expectedFilename = 'INV-'.$datePart.'-'.$invoiceId.'.pdf';
        $response->assertHeader('content-disposition', 'inline; filename="'.$expectedFilename.'"');
    }

    protected function login(User $user): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()->json('data.token');
    }

    protected function createInvoiceForOutlet(Outlet $outlet): int
    {
        $product = Product::factory()->create(['price' => 100000, 'stock_quantity' => 100, 'is_active' => true]);
        $user = $outlet->user;
        $token = $user->id === $this->outletUser->id ? $this->outletToken : $this->login($user);
        $response = $this->withToken($token)->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'idempotency_key' => 'pdf-parity-'.uniqid(),
        ])->assertCreated();

        $order = Order::findOrFail($response->json('data.id'));
        $this->withToken($this->adminToken)->putJson("/api/orders/{$order->id}/approve")->assertOk();

        return Invoice::where('order_id', $order->id)->value('id');
    }
}