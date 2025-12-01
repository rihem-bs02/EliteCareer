<?php
declare(strict_types=1);

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'web_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();

        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
        ]);
    }
}
