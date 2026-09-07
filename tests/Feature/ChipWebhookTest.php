<?php

namespace Tests\Feature;

use App\Enums\EventPaymentStatus;
use App\Enums\EventTransactionSource;
use App\Models\Event;
use App\Models\EventForm;
use App\Models\EventFormResponse;
use App\Models\EventPayment;
use App\Models\EventTransaction;
use App\Models\Organization;
use App\Models\OrganizationPaymentSetting;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ChipWebhookTest extends TestCase
{
    use RefreshDatabase;

    // Fixed, test-only RSA key pairs (not used anywhere outside this test).
    // openssl_pkey_new() can't generate keys at runtime on some PHP/Windows
    // installs (missing openssl.cnf), so these are pre-generated fixtures —
    // openssl_sign()/openssl_verify() themselves don't need that config file,
    // only key *generation* does.
    private const PRIVATE_KEY = <<<'PEM'
    -----BEGIN PRIVATE KEY-----
    MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDjajuvpeG/6b8n
    tXKmNT3iHWrmzKvYksbnZNqA2+S7Vgfl23f9+eWkwIdnripBmo+aKDxiu+4si7Fw
    R91to9TjieTLh8lIBS6q5r4fM5Ym+TGux7R8+MxgHtuGrBlzL2qqk1mH/GzhcK8L
    CaNCJx1mW+7dP7a18eAll0zxf0UGlIhBt7UsMNWPBJbgycUfcWVVonnIcwZtPNSL
    FWlD9JYAZMVWogn1rG/IBXNif2WCbb5PhMbchmghYtp6HAw3bRulZ4wplk/3ssnm
    cStJKzsdkePEJXHEQnlF4/8IjNhO39gY2h8fDovDF0IsqLWXMM0mOf//yD+1DYto
    j6NG3+VRAgMBAAECggEAHAOt6xcW7m6DyQc54p4r66MelGRxpen83SvoG9gavvYb
    3nWw/CUw5CEAfSXwGClLV9zthWcrsaqatt9veE5mjwohWG43fir/QvfOQ2c2L8Ji
    W6rHwd5fRNcASYCBWRZmADHLJWyT2Biqw4QSK7fIUB/ylqg+4H51k4PmK1i3ftYJ
    VegKPSiQKwbFPHOfbSwwNgA3KgP9Svzg4snwRTXOLsW0W4t7Mmr6xSiE58mNl1LT
    zzs8ojjd9wEsDIGn/vc7asLQVRBMszh2IVefc48o5+nqL0cPV0JrEw/+Vsdvzjmr
    WWC+AiaitKaWK18rzB+mb/Kh+KrHPY2sBcPbd6vUTQKBgQD2qrekzYuNDMWy25UO
    O6xD4xLSPirxs9tOTBxVAPV84Y5J0PhyrPNcHnxH2+MbMFWQ6SGV9Pg3Ml1hhIRG
    KqwNs9RLmLho6yFXFnChiSv4x1J3GK8chDVtxffXoOrToRQnwYnad+q6qN9yVFSK
    j7LE0zdHkEOYCazYHFoCkA3DRQKBgQDsBQll2EAiiqKDna2orq83DTgrkmT1a2S/
    NwsOgG55SRQjSDbZjXKy/bEnZNM/O/CdhgAiZAYUVew84z4JBEnQYzMO2VnU1M01
    xxNjWgwKCsCznzj0iJYlmvPrAVAcCzSGyr/p3xxenRELa75+Pe55GLfXNVCrfuxv
    PCLxhHLUnQKBgGqB6G3mut0aqLrECaZtqcJeaCAT6+MVsBoszwb9NQLJOfExpDWP
    7DzYhP1aOsPgqPG7WF8xuYPL4XpcB+lsP4JjJcXGmcnjzS+XZua5Hh17o2X9aI89
    nvxZQN0AhKUApn1MGkQVB2u1w1XQh/iUd7J5KSNjbWxWsSVXiJ4WqXqpAoGBAMjM
    fDNeqSn6AsuxQcbKX52ZrJk9YpF9/efE365FzDd52h0uWiP6+IOZ3LdkS2l4CH0s
    PT8FFDsG4wbmWqf3MwmM7CqM9qVhKvm+1hrnyGhev5XSN/Wrovp5e14L7uj9C4JG
    SsKhpBRG0vKBhz8GV7ZFNlttK6XMRUFZy2zpP3ztAoGAUPgTwIKlPU1uUrK0vH2t
    1jPoAQ+ppBYJP1lxuer115vizUG18NSGghHi5lclX062qyycR3O7iA2B9UgJDttu
    EAS5gD5kp3b2VqTJ1RvkOUQa1kJTmvzKOym7cQtNc55GvkIcrVcBx5AieFgjeH8z
    szhlYSysmhUZqD8fjXrfPT4=
    -----END PRIVATE KEY-----
    PEM;

    private const PUBLIC_KEY = <<<'PEM'
    -----BEGIN PUBLIC KEY-----
    MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA42o7r6Xhv+m/J7VypjU9
    4h1q5syr2JLG52TagNvku1YH5dt3/fnlpMCHZ64qQZqPmig8YrvuLIuxcEfdbaPU
    44nky4fJSAUuqua+HzOWJvkxrse0fPjMYB7bhqwZcy9qqpNZh/xs4XCvCwmjQicd
    Zlvu3T+2tfHgJZdM8X9FBpSIQbe1LDDVjwSW4MnFH3FlVaJ5yHMGbTzUixVpQ/SW
    AGTFVqIJ9axvyAVzYn9lgm2+T4TG3IZoIWLaehwMN20bpWeMKZZP97LJ5nErSSs7
    HZHjxCVxxEJ5ReP/CIzYTt/YGNofHw6LwxdCLKi1lzDNJjn//8g/tQ2LaI+jRt/l
    UQIDAQAB
    -----END PUBLIC KEY-----
    PEM;

    // A second, unrelated key pair — only its private half is used, to sign
    // a payload with the "wrong" key so the invalid-signature test proves
    // verification actually checks against the registered public key.
    private const OTHER_PRIVATE_KEY = <<<'PEM'
    -----BEGIN PRIVATE KEY-----
    MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQDMFGzHDqz6Gxe2
    VwcX+HZDQ/Qb3S71Qz3D5j+fxuXFKEepLw58BTuCobbGY2LWu5RgTDKvfpyZGZRB
    zE9VLxCgw2pmrdNPxtZU2pdZltAsYOZzsx9DpLbmjtb2ljHwISlIxAaaR62wRq53
    TUM7RxP9MuJd03vT+/Vmvmt/8Zl6gPvT2VT9pL+UxUIUyVdV9uPVjGdeMVmxkuo6
    COMmH3uJFTQvVkwLnVLhOEKi53jC/XXkzZBn7mluPMVbCdpP7RaMRjTY3CMtYDGd
    7INl1Ls0hWYikLQRl/OANUK2cARHKoLdH+NfWMNS5v8fy41woL9tq4HKqvFw4wkw
    CPQpqRfZAgMBAAECggEATDwHBHcBe+5Z9cXdwwEQIIGGc1A1Je6H6KGeu89fbr5k
    wpju9ro3qE1Diyl+NwvcWfqs5mzMD88D2G57Zi1OL1fAa/ncGY2D7C+54QAa5LEP
    9UGA2NJzdn3+ZB/oKLUiiGmds80530MQEkXc6wxatbdn4zfIpoAVBFv2qVU1iZJw
    kZhC2BHL86ggmD74sBdeyvvahjS35KBD/Tpdiee6rgKCAZf4pxvZT5CWqviFRQmg
    Aein41eFZrG7mE8Ov2nhaTLt3U1gWC+qDxP/p+C2FC1C+XwSDImcBNIOPWqFtPYG
    99DSVbio9cH5YLoawelAug6vzdXtKNCe+FpgLpSnzwKBgQD9dQDhoBkNBcnLms4t
    btbJBwnsmQeqwmefCvB/uOBTE8LHUnAPjqgr191GDrFW8wN5mPXi0G8ZMqZ6VxtL
    HZe9jGzWb9qfRf61vn+Nk0JntH1yZW5d+RJO0/1WmeEVtxOGbTq9Ahc8zHqkGoq7
    hMSoZJGjkVQYTubl2X35+tNFFwKBgQDOIJj2XZ4PPlffcE7MaEG64c5q1VDv+kcj
    ntVvebgN5+FcMDb5+fAtBM7HRj+NHBvgNBkz98eqztbjKZc5Evd2YeT5qo1lNP8y
    d9ratYtw1ore3/B6Qn/gLaXMG8wmfzG689pF7DTsTX8pK6VTkMUQAAPSOXexek5U
    0fznkfaAjwKBgC3YEfa9jIpsd5maQJy7JaJq4YKoE1jxnOiJhAK2H+0m7945rQdD
    WGvFucJIOg9uGTzPS/pglRfLr40FYGxvx9iDI9SNms+gS3f4Iv4qmqJDZUVhz1q0
    CHm1omcdojbZTHDOJQe27xkSK0SvgFR2qVOEDUu2p61V6DG+6yhiFAC1AoGAEhG3
    dvlT7sAnUdX1gmOtR2WeA615b9//tnao/SGtacKm9b7gQt+PF1MxkTRuQ79wDiJj
    BzQ0U5vYKIev0vf/q2f/e4dg57tSl6j8DWoWtCiKeeklmoIT1aIKw664IbPKtznD
    K5f+N6y/det2jbHGJJXrv9T70hoHndXelqor8q8CgYAHy3vR5gn4wTtI4gn6CCMJ
    0GZ1rrkMDIuqGGEk7xtoAUA2LOoxauKh6d6rhVBqFOW9Z1Y4y0e+yMAch8iOrcsz
    yF7Ko4lhw9XOZvQ2GdI+iZYEpwmf18XPp3xE3ZGSUtr+dS638K+00RVyNt/UKHiQ
    drbBB0oAd00ju/0tOAxsoA==
    -----END PRIVATE KEY-----
    PEM;

    private function sign(string $body, string $privateKey): string
    {
        openssl_sign($body, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    private function setupPayment(Organization $organization, array $paymentOverrides = []): EventPayment
    {
        return app(CurrentOrganization::class)->runFor($organization, function () use ($organization, $paymentOverrides) {
            OrganizationPaymentSetting::factory()->for($organization)->chipConfigured()->create();
            $event = Event::factory()->create();
            $eventForm = EventForm::factory()->published()->for($event)->create();
            $response = EventFormResponse::factory()->for($eventForm, 'eventForm')->create();

            return EventPayment::factory()->for($response, 'response')->create([
                'chip_purchase_id' => 'chip-purchase-1',
                'amount' => 50,
                ...$paymentOverrides,
            ]);
        });
    }

    private function postWebhook(string $body, string $signature): TestResponse
    {
        return $this->call('POST', route('chip.webhook'), [], [], [], [
            'HTTP_X_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    public function test_a_validly_signed_paid_callback_confirms_the_payment_and_creates_the_ledger_entry(): void
    {
        $organization = Organization::factory()->create();
        $payment = $this->setupPayment($organization);

        Http::fake([
            'https://gate.chip-in.asia/api/v1/public_key/' => Http::response(json_encode(self::PUBLIC_KEY)),
            'https://gate.chip-in.asia/api/v1/purchases/chip-purchase-1/' => Http::response([
                'status' => 'paid',
                'payment' => ['fee_amount' => 150],
            ]),
        ]);

        $body = json_encode(['id' => 'chip-purchase-1', 'status' => 'paid']);
        $signature = $this->sign($body, self::PRIVATE_KEY);

        $this->postWebhook($body, $signature)->assertOk();

        $payment->refresh();
        $this->assertSame(EventPaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->event_transaction_id);

        $transaction = EventTransaction::find($payment->event_transaction_id);
        $this->assertSame(EventTransactionSource::Gateway, $transaction->source);
        $this->assertSame('50.00', (string) $transaction->amount);
        $this->assertSame('1.50', (string) $transaction->gateway_fee_amount);
    }

    public function test_an_invalid_signature_is_rejected_and_nothing_is_confirmed(): void
    {
        $organization = Organization::factory()->create();
        $payment = $this->setupPayment($organization);

        Http::fake([
            'https://gate.chip-in.asia/api/v1/public_key/' => Http::response(json_encode(self::PUBLIC_KEY)),
            'https://gate.chip-in.asia/api/v1/purchases/chip-purchase-1/' => Http::response(['status' => 'paid']),
        ]);

        $body = json_encode(['id' => 'chip-purchase-1', 'status' => 'paid']);
        $signature = $this->sign($body, self::OTHER_PRIVATE_KEY);

        $this->postWebhook($body, $signature)->assertStatus(401);

        $this->assertSame(EventPaymentStatus::Pending, $payment->refresh()->status);
        $this->assertSame(0, EventTransaction::count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/purchases/chip-purchase-1/'));
    }

    public function test_a_missing_signature_header_is_rejected(): void
    {
        $organization = Organization::factory()->create();
        $payment = $this->setupPayment($organization);

        Http::fake([
            'https://gate.chip-in.asia/api/v1/public_key/' => Http::response(json_encode('-----BEGIN PUBLIC KEY-----fake-----END PUBLIC KEY-----')),
        ]);

        $body = json_encode(['id' => 'chip-purchase-1', 'status' => 'paid']);

        $this->call('POST', route('chip.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)
            ->assertStatus(401);

        $this->assertSame(EventPaymentStatus::Pending, $payment->refresh()->status);
    }

    public function test_an_unknown_purchase_id_is_acknowledged_without_error(): void
    {
        $body = json_encode(['id' => 'does-not-exist', 'status' => 'paid']);

        $this->call('POST', route('chip.webhook'), [], [], [], [
            'HTTP_X_SIGNATURE' => base64_encode('irrelevant'),
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        $this->assertSame(0, EventTransaction::count());
    }

    public function test_an_already_paid_payment_is_idempotent_and_skips_the_retrieve_call(): void
    {
        $organization = Organization::factory()->create();
        $payment = $this->setupPayment($organization, ['status' => EventPaymentStatus::Paid, 'paid_at' => now()]);

        Http::fake([
            'https://gate.chip-in.asia/api/v1/public_key/' => Http::response(json_encode(self::PUBLIC_KEY)),
        ]);

        $body = json_encode(['id' => 'chip-purchase-1', 'status' => 'paid']);
        $signature = $this->sign($body, self::PRIVATE_KEY);

        $this->postWebhook($body, $signature)->assertOk();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/purchases/chip-purchase-1/'));
        $this->assertSame(0, EventTransaction::count());
    }

    public function test_a_purchase_that_is_not_yet_paid_is_not_confirmed(): void
    {
        $organization = Organization::factory()->create();
        $payment = $this->setupPayment($organization);

        Http::fake([
            'https://gate.chip-in.asia/api/v1/public_key/' => Http::response(json_encode(self::PUBLIC_KEY)),
            'https://gate.chip-in.asia/api/v1/purchases/chip-purchase-1/' => Http::response(['status' => 'sent']),
        ]);

        $body = json_encode(['id' => 'chip-purchase-1', 'status' => 'paid']);
        $signature = $this->sign($body, self::PRIVATE_KEY);

        $this->postWebhook($body, $signature)->assertOk();

        $this->assertSame(EventPaymentStatus::Pending, $payment->refresh()->status);
        $this->assertSame(0, EventTransaction::count());
    }
}
