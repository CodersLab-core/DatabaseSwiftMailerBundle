<?php

namespace PaneeDesign\DatabaseSwiftMailerBundle\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * EmailRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class EmailRepository extends EntityRepository
{
    public function addEmail(Email $email)
    {
        $em = $this->getEntityManager();
        $email->setStatus(Email::STATUS_READY);
        $email->setRetries(0);
        $email->setCreatedAt(new \DateTime());

        $scheduledDbChanges = 0;
        $scheduledDbChanges += count($em->getUnitOfWork()->getScheduledEntityInsertions());
        $scheduledDbChanges += count($em->getUnitOfWork()->getScheduledEntityUpdates());
        $scheduledDbChanges += count($em->getUnitOfWork()->getScheduledEntityDeletions());
        $scheduledDbChanges += count($em->getUnitOfWork()->getScheduledCollectionUpdates());
        $scheduledDbChanges += count($em->getUnitOfWork()->getScheduledCollectionUpdates());

        $em->persist($email);

        // Flush only if there are not other db changes
        if ($scheduledDbChanges === 0) {
            $em->flush();
        }
    }

    public function getAllEmails($limit = null, $offset = null)
    {
        $qb = $this->createQueryBuilder('e');

        $qb
            ->addOrderBy('e.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
        ;

        return $qb->getQuery();
    }

    public function getEmailQueue($limit = 100)
    {
        $qb = $this->createQueryBuilder('e');

        $qb->where($qb->expr()->eq('e.status', ':status'))->setParameter(':status', Email::STATUS_READY);
        $qb->orWhere($qb->expr()->eq('e.status', ':status_1'))->setParameter(':status_1', Email::STATUS_FAILED);
        $qb->andWhere($qb->expr()->lt('e.retries', ':retries'))->setParameter(':retries', 10);


        $qb->addOrderBy('e.retries', 'ASC');
        $qb->addOrderBy('e.createdAt', 'ASC');
        if (empty($limit) === false) {
            $qb->setMaxResults($limit);
        }

        /** @var Email[] $emails */
        $emails = $qb->getQuery()->getResult();
        if (count($emails) > 0) {
            $ids = [];
            foreach ($emails as $email) {
                $ids[] = $email->getId();
            }
            $query = $this->_em->createQuery("UPDATE PedDatabaseSwiftMailerBundle:Email e SET e.status = '" . Email::STATUS_PROCESSING . "' WHERE e.id IN (:ids)");
            $query->setParameter(':ids', $ids);
            $query->execute();
        }

        return $emails;
    }

    public function markFailedSending(Email $email, \Exception $ex)
    {
        $email->setErrorMessage($ex->getMessage());
        $email->setStatus(Email::STATUS_FAILED);
        $email->setRetries(intval($email->getRetries()) + 1);
        $email->setUpdatedAt(new \DateTime());
        $em = $this->getEntityManager();
        $em->persist($email);
        $em->flush();
    }

    public function markCompleteSending(Email $email)
    {
        $email->setStatus(Email::STATUS_COMPLETE);
        $email->setSentAt(new \DateTime());
        $email->setErrorMessage('');
        $email->setUpdatedAt(new \DateTime());
        $em = $this->getEntityManager();
        $em->persist($email);
        $em->flush();
    }


}
