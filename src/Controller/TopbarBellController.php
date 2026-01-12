<?php

namespace App\Controller;

use App\Repository\SupplyRequestRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class TopbarBellController extends AbstractController
{
    public function bell(SupplyRequestRepository $repo): Response
    {
        $pendingCount = $repo->count(['status' => 'Pending']);
        $latestPending = $repo->findBy(['status' => 'Pending'], ['createdAt' => 'DESC'], 5);

        return $this->render('partials/_bell.html.twig', [
            'pendingCount' => $pendingCount,
            'latestPending' => $latestPending,
        ]);
    }
}
