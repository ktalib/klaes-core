<?php

namespace App\Http\Controllers\LandsOneStopShop;

use App\Models\LossOfDocumentApplication;

/** Loss of Document, OSS list: its own page and rows, the same rules as Land FC. */
class OssLossOfDocumentController extends LossOfDocumentController
{
    protected const MODULE = LossOfDocumentApplication::MODULE_OSS;
    protected const VIEW = 'lands_one_stop_shop.oss_loss_of_document';
}
