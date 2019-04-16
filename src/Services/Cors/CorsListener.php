<?php


namespace Markocupic\SacEventToolBundle\Services\Cors;

use Symfony\Component\HttpKernel\Event\FilterResponseEvent;


class CorsListener
{
    public function onKernelResponse(FilterResponseEvent $event)
    {

        $responseHeaders = $event->getResponse()->headers;
        $responseHeaders->set('Access-Control-Allow-Headers', 'origin, content-type, accept,DNT,X-CustomHeader,Keep-Alive,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type');
        $responseHeaders->set('Access-Control-Allow-Origin', '*');
        $responseHeaders->set('Access-Control-Allow-Credentials','true' );
        $responseHeaders->set('Access-Control-Allow-Methods', 'POST, GET, PUT, DELETE, PATCH, OPTIONS');
        $responseHeaders->set('Allow', 'POST, GET, PUT, DELETE, PATCH, OPTIONS');

    }
}
