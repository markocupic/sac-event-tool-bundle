<?php

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration;

enum CheckoutAction: string
{
	case CHECKOUT_STEP_LOGIN = 'login';
	case CHECKOUT_STEP_REGISTER = 'register';
	case CHECKOUT_STEP_CONFIRM = 'confirm';
	case  CHECKOUT_STEP_REGISTRATION_INTERRUPTED = 'registration_interrupted';
}
