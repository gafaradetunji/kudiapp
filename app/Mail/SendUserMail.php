<?php
namespace App\Mail;
        
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use MailerSend\Helpers\Builder\Variable;
use MailerSend\Helpers\Builder\Personalization;
use MailerSend\LaravelDriver\MailerSendTrait;

class SendUserMail extends Mailable
{
    use Queueable, SerializesModels, MailerSendTrait;
    protected $toAddress;
    protected $data;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($toAddress, $data)
    {
        $this->toAddress = $toAddress;
        $this->data = $data;
    }

    public function build()
    {
        $to = Arr::get($this->to, '0.address');

        return $this
        ->view('code', ['data' => $this->data])
        ->text('text', ['data' => $this->data])
        ->mailersend(
            template_id: null,
            variables: [
                new Variable($to, ['name' => 'Kudi Wallet'])
            ],
            tags: ['tag'],
            personalization: [
                new Personalization($to, [
                    'var' => 'variable',
                    'number' => 123,
                    'object' => [
                        'key' => 'object-value'
                    ],
                    'objectCollection' => [
                        [
                            'name' => 'John'
                        ],
                        [
                            'name' => 'Patrick'
                        ]
                    ],
                ])
            ],
            precedenceBulkHeader: true,
            // sendAt: new Carbon('2022-01-28 11:53:20'),
        );
    }
}