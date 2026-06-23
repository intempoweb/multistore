<?php

namespace App\Services\Storefront\Home\Presenters;

use App\Data\Storefront\HomePageInput;
use App\Models\Store;
use App\Services\Storefront\Home\HomePagePresenter;

final class DefaultHomePagePresenter implements HomePagePresenter
{
    public function supports(Store $store): bool
    {
        return true;
    }

    public function present(HomePageInput $input): array
    {
        $agentContextId = (string) $input->request->input('agent_context', '');

        return [
            'agentContextId' => $agentContextId,
            'contextParams' => $agentContextId !== '' ? ['agent_context' => $agentContextId] : [],
            'isAgentContext' => $input->request->session()->get('agent_mode') === true
                && $agentContextId !== ''
                && is_array($input->request->session()->get("agent_contexts.$agentContextId")),
        ];
    }
}
