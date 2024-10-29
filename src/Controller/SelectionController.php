<?php


namespace App\Controller;


use App\Entity\ImportedData;
use App\Logic\FileAccess;
use App\Parser\IEtuParser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/selection'), IsGranted('ROLE_SCOLA')]
class SelectionController extends AbstractController
{
    public function __construct(private FileAccess $file_access)
    {
    }

    #[Route('/rn', name: 'api_selection_rn')]
    public function api_get_selection_rn(Request $request, SerializerInterface $ser): JsonResponse
    {
        $bddData = $request->getSession()->get('data');
        $etu = $request->getSession()->get('students');

        foreach ($etu as $entry) {
            $entry->LoadFile($this->file_access->getTmpByMode(ImportedData::RN), $this->file_access->getDirByMode(ImportedData::RN));
        }

        $json = $ser->serialize(['data' => $bddData, 'students' => $etu], 'json', ['groups' => ['import:read', 'student:read']]);
        return new JsonResponse($json, 200, [], true);
    }

    #[Route('/attest', name: 'api_selection_attest')]
    public function api_get_selection_attest(Request $request, SerializerInterface $ser): JsonResponse
    {
        $bddData = $request->getSession()->get('data');
        $etu = $request->getSession()->get('students');

        foreach ($etu as $entry) {
            $entry->LoadFile($this->file_access->getTmpByMode(ImportedData::ATTEST), $this->file_access->getDirByMode(ImportedData::ATTEST));
        }

        $json = $ser->serialize(['data' => $bddData, 'students' => $etu], 'json', ['groups' => ['import:read', 'student:read']]);
        return new JsonResponse($json, 200, [], true);
    }

    /**
     * Reconstruit un document PDF avec les PDFs qui ont été transférés dans les dossiers étudiants.
     */
    #[Route('/rebuild/{id}', name: 'rebuild_doc')]
    public function reBuild(ImportedData $import, IEtuParser $parser): JsonResponse
    {
        $mode = $import->isRn() ? 0 : 1;
        $folder = "/tmp";

        if (str_contains($import->getPdfFilename(), '.pdf') === false)
            $fileName = $import->getPdfFilename();
        else
            $fileName = explode('.pdf', $import->getPdfFilename())[0];

        $fileName = $fileName . '_rebuild.pdf';
        $new_path = "$folder/$fileName";

        if ($mode == ImportedData::ATTEST) {
            $folder = $this->file_access->getAttest();
            $pattern = "*/" . $parser->getAttestFileName($import, '*');
        } else {
            $folder = $this->file_access->getRn();
            $pattern = "*/" . $parser->getReleveFileName($import, '*');
        }

        $files = glob($folder . $pattern);

//        // Trie des étudiants par nom,prenom
//        usort($transfered, function ($a, $b) {
//            $cmpNom = strcmp($a[0]->getName(), $b[0]->getName());
//            $cmpPrenom = strcmp($a[0]->getSurname(), $b[0]->getSurname());
//            return $cmpNom == 0 ? $cmpPrenom : $cmpNom;
//        });

        $cmd = "gs -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -sOutputFile='" . $new_path . "' ";
        foreach ($files as $file) {
            $filepath = str_replace(' ', "\ ", $file);
            $filepath = str_replace('(', "\(", $filepath);
            $filepath = str_replace(')', "\)", $filepath);
            $cmd .= $filepath . " ";
        }

        try {
            $proc = Process::fromShellCommandline($cmd);
            $proc->setTimeout(null);
            $proc->setIdleTimeout(null);
            $proc->run();

            $response = $this->file($new_path, $fileName);
            $response->send();

            if (file_exists($new_path))
                unlink($new_path);

        } catch (\Exception $e) {
            return new JsonResponse("Erreur lors de la reconstruction du document", 500);
        }

        return new JsonResponse("ok");
    }
}