<?php


namespace App\Controller;


use App\Logic\CustomFinder;
use App\Logic\FileAccess;
use App\Logic\LDAP;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/scola'), IsGranted('ROLE_SCOLA')]
class ScolaController extends AbstractController
{
    public function __construct(private FileAccess $file_access, private CustomFinder $finder)
    {
    }

    #[Route('/search', name: 'api_search')]
    public function api_search(Request $request, LDAP $ldap): JsonResponse
    {
        $searchField = $request->getContent();

        $users = $ldap->search("(|(sn=$searchField)(CLFDcodeEtu=$searchField))", "ou=people,",
            ["eduPersonAffiliation", "CLFDcodeEtu", "sn", "givenName", "supannEntiteAffectationPrincipale"]);

        $filtered_users = $this->getFilteredUsers($users);

        return new JsonResponse($filtered_users);
    }

    private function getFilteredUsers(array $users): array
    {
        $filtered_users = [];
        foreach ($users as $user) {
            if ($user->hasAttribute("CLFDcodeEtu") && in_array("student", $user->getAttribute("eduPersonAffiliation"))) {
                $num = $user->getAttribute("CLFDcodeEtu")[0];
                $nb_rn = count($this->finder->getFilesName($this->file_access->getRn() . $num . '/'));
                $nb_attest = count($this->finder->getFilesName($this->file_access->getAttest() . $num . '/'));

                $simpleUser = [
                    'CLFDcodeEtu' => $num,
                    'sn' => $user->getAttribute('sn')[0],
                    'givenName' => $user->getAttribute('givenName')[0],
                    'supannEntiteAffectationPrincipale' => $user->getAttribute('supannEntiteAffectationPrincipale')[0],
                    'nb_docs' => $nb_rn + $nb_attest
                ];

                $filtered_users[] = $simpleUser;
            }
        }

        return $filtered_users;
    }

}