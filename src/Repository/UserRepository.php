<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    /**
     * UserRepository constructor.
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Deletes a user
     * @param $id
     * @return mixed
     */
    public function delete($id)
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'DELETE App\Entity\User u WHERE u.id = :id'
        )->setParameter('id', $id);
        return $query->getResult();
    }

    /**
     * Finds a user by credentials
     * @param $email
     * @param $password
     * @return User[] Returns an array of User objects
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findByCredentials($email, $password)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')
            ->andWhere('u.password = :password')
            ->setParameter('email', $email)
            ->setParameter('password', $password)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
