<?php

namespace App\Controller\Admin;

use App\Entity\Utilisateur;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UtilisateurCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Utilisateur::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('nom', 'Nom'),
            TextField::new('prenom', 'Prénom'),
            EmailField::new('email', 'Email'),
            TextField::new('telephone', 'Téléphone')->hideOnIndex(),
            TextField::new('service', 'Service'),
            ChoiceField::new('roles', 'Rôles')
                ->setChoices([
                    'Employé' => 'ROLE_EMPLOYE',
                    'Superviseur' => 'ROLE_SUPERVISEUR',
                    'Responsable' => 'ROLE_RESPONSABLE',
                    'Administrateur' => 'ROLE_ADMIN',
                ])
                ->allowMultipleChoices(),
        ];
    }
}
