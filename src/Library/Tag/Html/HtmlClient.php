<?php

namespace ControleOnline\Library\Tag\Html;

use ControleOnline\Entity\SalesOrder;
use ControleOnline\Library\Tag\AbstractTag;
use Proner\PhpPimaco\Pimaco;
use Proner\PhpPimaco\Tag;

class HtmlClient extends AbstractTag
{
    public function getPdf(SalesOrder $orderData)
    {
        return $this->getPdfTagData($orderData);
    }

    protected function getPdfTagData(SalesOrder $orderData)
    {
        $params = $this->_getOrdersTemplateParams($orderData);
        $twigFile = 'tag/A4Tag.html.twig';
        $html = $this->twig->render($twigFile, $params);
        return $html;
    }
}
