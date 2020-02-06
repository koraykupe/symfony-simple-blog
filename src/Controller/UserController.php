<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserEditType;
use App\Form\UserLoginType;
use App\Form\UserRegisterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserController extends AbstractController
{
    private $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * @Route("/", name="login")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
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

            $user = $userRepository->findOneBy(
                ['email' => $userLoginData->getEmail(), 'password' => $encodedPassword]
            );

            if ($user) {
                $this->session->set('logged_in_user', $user->getId());
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
     * @Route("/user/edit", name="user_edit")
     * @param Request                      $request
     * @param UserPasswordEncoderInterface $encoder
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function edit(Request $request, UserPasswordEncoderInterface $encoder)
    {
        $userId         = $this->session->get('logged_in_user');
        $userRepository = $this->getDoctrine()
                               ->getManager()
                               ->getRepository('App:User');
        $user           = $userRepository->find($userId);

        $defaultData = ['name' => $user->getName(), 'email' => $user->getEmail()];
        $form        = $this->createFormBuilder($defaultData)->add('email', EmailType::class)
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
     * @Route("/user/register", name="user_register")
     * @param Request                      $request
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @return \Symfony\Component\HttpFoundation\Response
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
            $em->flush();

            $this->session->set('logged_in_user', $user->getId());

            $this->addFlash(
                'success',
                'Your account has been created successfully.'
            );

            return $this->redirectToRoute('login');
        }
        return $this->render('user/register.html.twig', [
            'controller_name' => 'UserController',
            'form'            => $form->createView(),
        ]);
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logout()
    {
        $this->session->clear();
        return $this->redirectToRoute('login');
    }

    /**
     * @Route("/user/delete", name="user_selete")
     */
    public function delete()
    {
        $userId = $this->session->get('logged_in_user');

        $entityManager = $this->getDoctrine()->getManager();
        $user          = $entityManager->getRepository(User::class)->find($userId);

        if (!$user) {
            throw $this->createNotFoundException(
                'No user found for id ' . $userId
            );
        }

        $entityManager->remove($user);
        $entityManager->flush();

        $this->addFlash(
            'success',
            'Your account has been deleted successfully.'
        );

        return $this->logout();

    }
}
