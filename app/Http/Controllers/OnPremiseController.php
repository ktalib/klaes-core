<?php

namespace App\Http\Controllers;

use App\Services\LegalSearchService;

class OnPremiseController extends LegalSearchController
{
    protected string $pageTitle = 'On-Premise - Pay-per-Search';
    protected string $viewPrefix = 'legal_search';
    protected string $searchRouteName = 'onpremise.search';
    protected string $watermarkText = 'PAY-PER-SEARCH';
    protected string $printTemplateRouteName = 'legal_search.print.onpremise';
    protected string $printManagerDocType = 'Legal Search Pay-Per-Search';

    public function __construct(LegalSearchService $searchService)
    {
        parent::__construct($searchService);
    }
}
