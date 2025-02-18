<?php

namespace App\Controller;

use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserGroup;
use App\Logic\LDAP;
use App\Repository\GroupRepository;
use App\Repository\ImportedDataRepository;
use App\Repository\UserGroupRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/user'), IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(private UserRepository $userRepository, private GroupRepository $groupRepository)
    {
    }

    #[Route('/', name: 'get_users', methods: ['GET'])]
    public function getUsers(ImportedDataRepository $importRepo, LDAP $ldap): JsonResponse
    {
        $bddUsers = $this->userRepository->findAll();
        foreach ($bddUsers as $user) {
            $user->setOld(false);
        }

        $bddUserNames = array_map(function ($user) {
            return $user->getUsername();
        }, $bddUsers);

        // get all imported_data where username not in $bddUserNames
        $oldUsernames = $importRepo->findUsernameByNotIn($bddUserNames);

        if (empty($oldUsernames)) {
            return $this->json($bddUsers);
        }

        $usernames = array_map(function ($username) {
            return $username['username'];
        }, $oldUsernames);


        $ldapUsers = $ldap->search(
            '(|' . implode('', array_map(fn($username) => "(uid=$username)", $usernames)) . ')',
            "ou=people,",
            ["mail", "givenName", "sn", "supannEntiteAffectationPrincipale", "uid"]
        );

        $oldUsers = [];
        foreach ($ldapUsers as $ldapUser) {
            $username = current($ldapUser->getAttribute('uid'));
            $oldUser = new User($username, [], "");
            $oldUser->setNom(current($ldapUser->getAttribute('sn')));
            $oldUser->setPrenom(current($ldapUser->getAttribute('givenName')));
            $oldUser->setComposante(current($ldapUser->getAttribute('supannEntiteAffectationPrincipale') ?? []) ?? "");
            $oldUser->setOld(true);
            $oldUsers[] = $oldUser;
        }

        return $this->json(array_merge($bddUsers, $oldUsers));
    }

    #[Route('/search', name: 'find_users', methods: ['POST'])]
    public function findUsers(Request $request, LDAP $ldap): JsonResponse
    {
        $userIdentifier = json_decode($request->getContent())->user;

        $ldapUsers = $ldap->search("(&(|(uid=$userIdentifier)(sn=$userIdentifier))(CLFDstatus=9))",
            "ou=people,", ["mail", "givenName", "sn", "supannEntiteAffectationPrincipale", "uid"]);

        $users = [];
        foreach ($ldapUsers as $ldapUser) {

            if ($ldapUser->getAttribute('supannEntiteAffectationPrincipale') === null)
                continue;

            $users[] = [
                'username' => current($ldapUser->getAttribute('uid')),
                'nom' => current($ldapUser->getAttribute('sn')),
                'prenom' => current($ldapUser->getAttribute('givenName')),
                'composante' => current($ldapUser->getAttribute('supannEntiteAffectationPrincipale')),
                'email' => current($ldapUser->getAttribute('mail'))
            ];
        }

        return $this->json($users);
    }

    #[Route('/{id}', name: 'delete_user', methods: ['DELETE'])]
    public function deleteUser(User $user, UserGroupRepository $userGroupRepository, ImportedDataRepository $importRepo): JsonResponse
    {
        $userGroups = $userGroupRepository->findBy(['username' => $user->getUsername()]);

        $imports = $importRepo->findBy(['username' => $user->getUsername()]);

        foreach ($userGroups as $userGrp) {

            if ($userGrp->isResponsable() || empty($imports)) {
                $userGrp->getGroupe()->removeUserGroup($userGrp);
                $userGroupRepository->delete($userGrp);
            }
        }

        $this->userRepository->delete($user);
        return $this->json(['old' => !empty($imports)]);
    }

    #[Route('/', name: 'add_user', methods: ['POST'])]
    public function addUser(Request $request, LDAP $ldap, EntityManagerInterface $em): JsonResponse
    {
        $userIdentifier = json_decode($request->getContent())->user;

        $bddUser = $this->userRepository->findOneBy(['username' => $userIdentifier]);
        if (isset($bddUser))
            return $this->json(['message' => "Utilisateur [$userIdentifier] déjà existant"], 400);

        $users = $ldap->search("(uid=$userIdentifier)", "ou=people,", ["mail", "givenName", "sn", "supannEntiteAffectationPrincipale"]);

        $user = empty($users) ? null : current($users);

        if (!isset($user))
            return $this->json(['message' => "Utilisateur [$userIdentifier] introuvable"], 404);

        $bddUser = new User($userIdentifier, ['ROLE_SCOLA'], current($user->getAttribute('mail')));
        $bddUser->setNom(current($user->getAttribute('sn')));
        $bddUser->setPrenom(current($user->getAttribute('givenName')));
        $bddUser->setComposante(current($user->getAttribute('supannEntiteAffectationPrincipale')));
        $em->persist($bddUser);
        $em->flush();

        return $this->json($bddUser);
    }

    #[Route('/group', name: 'get_groups', methods: ['GET'])]
    public function getGroups(SerializerInterface $ser): JsonResponse
    {
        $json = $ser->serialize($this->groupRepository->findAll(), 'json', ['groups' => 'api:group']);

        return new JsonResponse($json, 200, [], true);
    }

    #[Route('/group/{id}', name: 'delete_group', methods: ['DELETE'])]
    public function deleteGroup(Group $group): JsonResponse
    {
        $this->groupRepository->delete($group);
        return $this->json(['message' => 'Group deleted']);
    }

    #[Route('/group', name: 'add_group', methods: ['POST'])]
    public function addGroup(Request $request, EntityManagerInterface $em, SerializerInterface $ser): JsonResponse
    {
        $groupDto = json_decode($request->getContent())->group;

        $bddGroup = isset($groupDto->id) ? $this->groupRepository->find($groupDto->id) : null;
        if (isset($bddGroup)) {
            foreach ($bddGroup->getUserGroups() as $userGroup) {
                $bddGroup->removeUserGroup($userGroup);
                $em->remove($userGroup);
            }
        } else {
            $bddGroup = new Group();
        }

        $bddGroup->setLibelle($groupDto->libelle);

        foreach ($groupDto->userGroups as $userGroupDto) {
            $userGroup = new UserGroup();
            $userGroup->setUsername($userGroupDto->username);
            $userGroup->setResponsable($userGroupDto->responsable);
            $bddGroup->addUserGroup($userGroup);
        }

        if (!$bddGroup->isValid()) {
            return $this->json(['message' => 'Le groupe doit avoir au moins un responsable'], 400);
        }

        $em->persist($bddGroup);
        $em->flush();

        $json = $ser->serialize($bddGroup, 'json', ['groups' => 'api:group']);

        return new JsonResponse($json, 200, [], true);
    }
}