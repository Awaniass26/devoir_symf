<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\Client;
use App\Entity\Article;
use App\Repository\ClientRepository;
use App\Repository\ArticleRepository;
use App\Repository\CommandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\CommandeArticle;


class CommandeController extends AbstractController
{
    
    #[Route('/commande', name: 'commande_create', methods: ["GET", "POST"])]
public function createCommande(
    Request $request,
    ClientRepository $clientRepository,
    ArticleRepository $articleRepository,
    EntityManagerInterface $em
): Response {
    $client = null;
    $commande = new Commande();
    $total = 0;

    $articlesDisponibles = $articleRepository->findAll();

    if ($request->isMethod('POST') && $request->get('phone')) {
        $clientPhone = $request->get('phone');
        $client = $clientRepository->findOneBy(['telephone' => $clientPhone]);

        if ($client) {
            $commande->setClient($client);
            $commande->setDateAt(new \DateTimeImmutable());
        } else {
            return $this->render('commande/new.html.twig', [
                'client_not_found' => true,
                'commande' => $commande,
                'client' => $client,
                'articles' => $articlesDisponibles,
                'total' => $total
            ]);
        }
    }

    if ($request->isMethod('POST') && $request->get('articles')) {
        $articleIds = $request->get('articles');
        $quantities = $request->get('quantity');
        $prices = $request->get('price');

        if (is_array($articleIds) && count($articleIds) > 0) {
            foreach ($articleIds as $index => $id) {
                $article = $articleRepository->find($id);
                $quantity = $quantities[$index];
                $price = $prices[$index];

                if ($article && $article->getQuantite() >= $quantity) {
                    $commandeArticle = new CommandeArticle();
                    $commandeArticle->setArticle($article);
                    $commandeArticle->setQuantite($quantity);
                    $commandeArticle->setPrix($price);

                    $commande->addCommandeArticle($commandeArticle);

                    $article->setQuantite($article->getQuantite() - $quantity);

                    $total += $quantity * $price;
                }
            }

            $commande->setMontantTotal($total);

            $em->persist($commande);
            $em->flush();
        }
    }

    return $this->render('commande/new.html.twig', [
        'commande' => $commande,
        'client' => $client,
        'articles' => $articlesDisponibles,
        'total' => $total
    ]);
}

    
    
     #[Route('/commande/delete_article/{id}', name:'commande_delete_article', methods:'POST')]
    
    public function deleteArticle(int $id, CommandeRepository $commandeRepository): Response
    {
        $commandeArticle = $commandeRepository->find($id);
        if ($commandeArticle) {
            $commande = $commandeArticle->getCommande();
            $commande->removeArticle($commandeArticle);

            $article = $commandeArticle->getArticle();
            $article->setQuantite($article->getQuantite() + $commandeArticle->getQuantite());

            $commandeRepository->save($commande);
            return $this->redirectToRoute('commande_create');
        }

        return $this->redirectToRoute('commande_create');
    }


    
     #[Route('/commande/update_article/{id}', name:'commande_update_article', methods:"POST")]
    
    public function updateArticle(int $id, Request $request, CommandeRepository $commandeRepository): Response
    {
        $commandeArticle = $commandeRepository->find($id);
        if ($commandeArticle) {
            $newPrice = $request->get('price');
            $newQuantity = $request->get('quantity');

            if ($newQuantity > 0 && $commandeArticle->getArticle()->getQuantite() >= $newQuantity) {
                $commandeArticle->setPrix($newPrice);
                $commandeArticle->setQuantite($newQuantity);

                $article = $commandeArticle->getArticle();
                $article->setQuantite($article->getQuantite() - $newQuantity);

                $commandeRepository->save($commandeArticle->getCommande());
            }

            return $this->redirectToRoute('commande_create');
        }

        return $this->redirectToRoute('commande_create');
    }
}

