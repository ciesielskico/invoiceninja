<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Account;

use App\Events\Account\AccountCreated;
use App\Jobs\Company\CreateCompany;
use App\Jobs\Company\CreateCompanyToken;
use App\Jobs\User\CreateUser;
use App\Models\Account;
use App\Models\User;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreateAccount
{

    use Dispatchable;

    protected $request;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct(array $request)
    {

        $this->request = $request;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() : ?Account
    {
        /*
         * Create account
         */
        $account = Account::create($this->request);
        /*
         * Create company
         */
        $company = CreateCompany::dispatchNow($this->request, $account);
        $company->load('account');
        /*
         * Set default company
         */
        $account->default_company_id = $company->id;
        $account->save();
        /*
         * Create user
         */
        $user = CreateUser::dispatchNow($this->request, $account, $company, true); //make user company_owner
        /*
         * Required dependencies
         */
        if($user)
            auth()->login($user, false); 

        $user->setCompany($company);
        /*
         * Create token
         */
        $company_token = CreateCompanyToken::dispatchNow($company, $user);
        /*
         * Login user
         */
        //Auth::loginUsingId($user->id, true);
        /*
         * Fire related events
         */
        if($user)
            event(new AccountCreated($user));
        
        return $account;
    }
}
