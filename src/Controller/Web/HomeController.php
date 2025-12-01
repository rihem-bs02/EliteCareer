<?php
declare(strict_types=1);

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'web_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('home/index.html.twig');
    }
}
