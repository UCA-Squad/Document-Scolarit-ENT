<?php


namespace App\Controller;


use App\Entity\History;
use App\Logic\FileAccess;
use App\Parser\EtuParser;
use App\Repository\ImportedDataRepository;
use App\Repository\UserGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/monitoring'), IsGranted("ROLE_SCOLA")]
class MonitoringController extends AbstractController
{
    #[Route('/rn', name: 'get_monitoring_rn')]
    public function get_monitoring_rn(ImportedDataRepository $importedDataRepository, SerializerInterface $ser, UserGroupRepository $userGroupRepo): JsonResponse
    {
        if (in_array("ROLE_ADMIN", $this->getUser()->getRoles()))
            $imports = $importedDataRepository->findAllRns();
        else if (!empty($usernames = $userGroupRepo->getUsernamesByResponsable($this->getUser()->getUserIdentifier())))
            $imports = $importedDataRepository->findRnsByUsernames($usernames);
        else
            $imports = $importedDataRepository->findAllRns($this->getUser()->getUserIdentifier());

        usort($imports, function ($a, $b) {
            return $a->getLastHistory()->getDate() < $b->getLastHistory()->getDate();
        });

        $json = $ser->serialize($imports, 'json', ['groups' => 'import:read']);

        return new JsonResponse($json, 200, [], true);
    }

    #[Route('/attest', name: 'get_monitoring_attest')]
    public function monitoring_attest(ImportedDataRepository $importedDataRepository, SerializerInterface $ser, UserGroupRepository $userGroupRepo): Response
    {
        if (in_array("ROLE_ADMIN", $this->getUser()->getRoles()))
            $imports = $importedDataRepository->findAllAttests();
        else if (!empty($usernames = $userGroupRepo->getUsernamesByResponsable($this->getUser()->getUserIdentifier())))
            $imports = $importedDataRepository->findAttestsByUsernames($usernames);
        else
            $imports = $importedDataRepository->findAllAttests($this->getUser()->getUserIdentifier());

        usort($imports, function ($a, $b) {
            return $a->getLastHistory()->getDate() < $b->getLastHistory()->getDate();
        });

        $json = $ser->serialize($imports, 'json', ['groups' => 'import:read']);

        return new JsonResponse($json, 200, [], true);
    }

    #[Route('/delete', name: 'api_delete_file', methods: ['POST'])]
    public function removeFile(Request $request, ImportedDataRepository $repo, FileAccess $fileAccess, EtuParser $parser, EntityManagerInterface $em, SerializerInterface $ser, UserGroupRepository $userGroupRepo): JsonResponse
    {
        $params = json_decode($request->getContent(), true);

        $dataId = $params['dataId'];
        $numsEtu = $params['numsEtu'];

        $data = $repo->find($dataId);
        if (!isset($data) || empty($numsEtu))
            return $this->json('Missing params', 403);

        if (!$this->isGranted("ROLE_ADMIN") && !$userGroupRepo->hasRightOn($data, $this->getUser()->getUserIdentifier()))
            return $this->json('Unauthorized', 403);

        if ($data->isRn())
            $folder = $fileAccess->getRn();
        else
            $folder = $fileAccess->getAttest();


        $data->addHistory(new History(0, History::Modified));

        $nbDocRemoved = 0;
        foreach ($numsEtu as $numEtu) {

            if ($data->isRn())
                $filename = $parser->getReleveFileName($data, $numEtu);
            else
                $filename = $parser->getAttestFileName($data, $numEtu);

            if (!file_exists($folder . $numEtu . "/" . $filename))
                return $this->json('Impossible de supprimer le document', 500);

            unlink($folder . $numEtu . "/" . $filename);
            $nbDocRemoved++;
            $data->getHistory()->last()->setNbFiles(-$nbDocRemoved);
        }

        $data->setNbStudents($data->getNbStudents() - $nbDocRemoved);

        $em->persist($data);
        $em->flush();

        $json = $ser->serialize($data, 'json', ['groups' => 'import:read']);

        return new JsonResponse($json, 200, [], true);
    }
}