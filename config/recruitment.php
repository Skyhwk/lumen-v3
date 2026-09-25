<?php

$portalBase = env('PORTAL_RECRUITMENT_PUBLIC_BASE');
if (!$portalBase) {
    $portalV4 = rtrim((string) env('PORTALV4', 'https://portal.intilab.com'), '/');
    $portalBase = $portalV4 . '/public/recruitment';
}

return [
    'portal_public_base' => rtrim((string) $portalBase, '/'),
];
