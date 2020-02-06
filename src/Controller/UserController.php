<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserLoginType;
use App\Form\UserRegisterType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserController extends AbstractController
{
    private $session;

    /**
     * UserController constructor.
     * @param SessionInterface $session
     */
    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * Dashboard page with login form and register link
     * @Route("/", name="login")
     * @param Request                      $request
     * @param UserPasswordEncoderInterface $encoder
     * @return RedirectResponse|Response
     */
    public function index(Request $request, UserPasswordEncoderInterface $encoder)
    {
        $form = $this->createForm(UserLoginType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userLoginData = $form->getData();

            $userRepository = $this->getDoctrine()
                                   ->getManager()
                                   ->getRepository('App:User');

            $user            = new User();
            $plainPassword   = $userLoginData->getPassword();
            $encodedPassword = $encoder->encodePassword($user, $plainPassword);

            $user = $userRepository->findByCredentials($userLoginData->getEmail(), $encodedPassword);

            if ($user) {
                $this->setLoggedInUserId($user->getId());
                return $this->redirectToRoute('user_edit');
            }

            $this->addFlash(
                'errors',
                'User is not found with given email and password combination.'
            );
            return $this->redirectToRoute('login');
        }

        return $this->render('user/login.html.twig', [
            'form'      => $form->createView(),
            'logged_in' => $this->session->has('logged_in_user'),
        ]);
    }

    /**
     * User edit details page
     * @Route("/user/edit", name="user_edit")
     * @param Request                      $request
     * @param UserPasswordEncoderInterface $encoder
     * @return Response
     * @throws \Exception
     */
    public function edit(Request $request, UserPasswordEncoderInterface $encoder)
    {
        $userRepository = $this->getDoctrine()->getManager()->getRepository('App:User');
        try {
            $user = $userRepository->find($this->getLoggedInUserId());
        } catch (\Exception $e) {
            return $this->userNotLoggedIn();
        }

        $form = $this->buildUserEditForm($user);
        $form->handleRequest($request);


        if ($user && $form->isSubmitted() && $form->isValid()) {
            $userFormData = $form->getData();

            // Check if current password is valid
            $isCurrentPasswordValid = $encoder->isPasswordValid($user, $userFormData['password']);
            if ($isCurrentPasswordValid === false) {
                $this->addFlash(
                    'error',
                    'Current password is wrong!'
                );
                return $this->redirectToRoute('user_edit');
            }

            $user->setName($userFormData['name']);
            $user->setEmail($userFormData['email']);

            if (isset($userFormData['new_password']) && $userFormData['new_password']) {
                $user->setPassword($encoder->encodePassword($user, $userFormData['new_password']));
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            $this->addFlash(
                'success',
                'Your data has been updated!'
            );

        }

        return $this->render('user/edit.html.twig', [
            'controller_name' => 'UserController',
            'user'            => $user,
            'form'            => $form->createView(),
        ]);
    }

    /**
     * Registration form and handling data for new user
     * @Route("/user/register", name="user_register")
     * @param Request                      $request
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @return Response
     */
    public function register(
        Request $request,
        UserPasswordEncoderInterface $passwordEncoder
    ) {
        $form = $this->createForm(UserRegisterType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userData = $form->getData();

            $user = new User();
            $user->setEmail($userData->getEmail());
            $user->setName($userData->getName());
            $user->setPassword($passwordEncoder->encodePassword(
                $user,
                $userData->getPassword()
            ));

            $em = $this->getDoctrine()->getManager();
            $em->persist($user);

            try {
                $em->flush();
                $this->setLoggedInUserId($user->getId());
                $this->addFlash(
                    'success',
                    'Your account has been created successfully.'
                );
            } catch (UniqueConstraintViolationException $e) {
                $this->addFlash(
                    'errors',
                    'Given email is already registered.'
                );
            }


            return $this->redirectToRoute('login');
        }
        return $this->render('user/register.html.twig', [
            'controller_name' => 'UserController',
            'form'            => $form->createView(),
        ]);
    }

    /**
     * Deletes all sessions and logs out user
     * @Route("/logout", name="logout")
     */
    public function logout()
    {
        $this->session->clear();
        $this->addFlash(
            'success',
            'You logged out.'
        );

        return $this->redirectToRoute('login');
    }

    /**
     * Deletes logged in user from database
     * @Route("/user/delete", name="user_selete")
     * @throws \Exception
     */
    public function delete()
    {
        try {
            $userId = $this->getLoggedInUserId();
        } catch (\Exception $e) {
            return $this->userNotLoggedIn();
        }
        $this->getDoctrine()->getRepository(User::class)->delete($userId);

        $this->addFlash(
            'success',
            'Your account has been deleted successfully.'
        );

        return $this->logout();
    }

    /**
     * Sets user id to session
     * @param $id
     * @return mixed
     */
    private function setLoggedInUserId($id)
    {
        return $this->session->set('logged_in_user', $id);
    }

    /**
     * Gets user id from the session
     * @param $id
     * @return mixed
     * @throws \Exception
     */
    private function getLoggedInUserId()
    {
        if ($this->session->has('logged_in_user')) {
            return $this->session->get('logged_in_user');
        }

        throw new \RuntimeException('User not logged in');
    }

    /**
     * Builds user edit form from array
     * @param $user
     * @return \Symfony\Component\Form\FormInterface
     */
    private function buildUserEditForm($user): \Symfony\Component\Form\FormInterface
    {
        $defaultData = ['name' => $user->getName(), 'email' => $user->getEmail()];
        return $this->createFormBuilder($defaultData)->add('email', EmailType::class)
                    ->add('name', TextType::class)
                    ->add('password', PasswordType::class)
                    ->add('new_password', RepeatedType::class, [
                        'type'            => PasswordType::class,
                        'invalid_message' => 'The password fields must match.',
                        'required'        => false,
                        'first_options'   => ['label' => 'New Password'],
                        'second_options'  => ['label' => 'Repeat New Password'],
                    ])
                    ->add('submit', SubmitType::class)
                    ->getForm();
    }

    /**
     * Redirect user to login page with a flash message
     * @return RedirectResponse
     */
    private function userNotLoggedIn(): RedirectResponse
    {
        $this->addFlash(
            'errors',
            'Please login first!'
        );
        return $this->redirectToRoute('login');
    }
}
