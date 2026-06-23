<?php

namespace App\Services\Storefront\Home;

use App\Data\Storefront\HomePageInput;
use App\Services\Storefront\Home\Presenters\CiakHomePagePresenter;
use App\Services\Storefront\Home\Presenters\DefaultHomePagePresenter;
use App\Services\Storefront\Home\Presenters\FipellHomePagePresenter;
use App\Services\Storefront\Home\Presenters\IntempoDistributionHomePagePresenter;

final class HomePageViewDataBuilder
{
    public function __construct(
        private CiakHomePagePresenter $ciak,
        private FipellHomePagePresenter $fipell,
        private IntempoDistributionHomePagePresenter $intempoDistribution,
        private DefaultHomePagePresenter $default,
    ) {}

    public function build(HomePageInput $input): array
    {
        foreach ($this->presenters() as $presenter) {
            if ($presenter->supports($input->store)) {
                return $presenter->present($input);
            }
        }

        return $this->default->present($input);
    }

    private function presenters(): array
    {
        return [$this->ciak, $this->fipell, $this->intempoDistribution, $this->default];
    }
}
