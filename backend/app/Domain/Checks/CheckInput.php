<?php

namespace App\Domain\Checks;

use App\Domain\Checks\ValueObjects\Address;
use App\Domain\Checks\ValueObjects\Amount;
use App\Domain\Checks\ValueObjects\Network;
use App\Domain\Checks\ValueObjects\ScriptSource;

/**
 * Raw observations submitted for a single check run — the frontend's
 * account of what it saw on the payment page. Built from the exact request
 * fields of POST /api/checks (see backend-conventions → API contract);
 * `null` means "not observed" / "could not be determined", not "empty".
 *
 * Every value is wrapped into its value object here so the rest of Domain
 * only ever compares value objects, never raw strings — the wrapping is a
 * type change, not a normalization of the observed value.
 */
final readonly class CheckInput
{
    public Address $displayedAddress;

    public Amount $displayedAmount;

    public Network $displayedNetwork;

    public ?Address $qrAddress;

    public ?Amount $qrAmount;

    public ?Network $qrNetwork;

    public ?Address $copyButtonValue;

    public ?Address $addressAfterWatchWindow;

    /** @var ScriptSource[]|null */
    public ?array $pageScripts;

    /**
     * @param  string[]|null  $pageScripts
     */
    public function __construct(
        string $displayedAddress,
        string $displayedAmount,
        string $displayedNetwork,
        ?string $qrAddress,
        ?string $qrAmount,
        ?string $qrNetwork,
        ?string $copyButtonValue,
        ?string $addressAfterWatchWindow,
        ?array $pageScripts,
    ) {
        $this->displayedAddress = new Address($displayedAddress);
        $this->displayedAmount = new Amount($displayedAmount);
        $this->displayedNetwork = new Network($displayedNetwork);
        $this->qrAddress = $qrAddress !== null ? new Address($qrAddress) : null;
        $this->qrAmount = $qrAmount !== null ? new Amount($qrAmount) : null;
        $this->qrNetwork = $qrNetwork !== null ? new Network($qrNetwork) : null;
        $this->copyButtonValue = $copyButtonValue !== null ? new Address($copyButtonValue) : null;
        $this->addressAfterWatchWindow = $addressAfterWatchWindow !== null
            ? new Address($addressAfterWatchWindow)
            : null;
        $this->pageScripts = $pageScripts !== null
            ? array_map(static fn (string $script): ScriptSource => new ScriptSource($script), $pageScripts)
            : null;
    }
}
