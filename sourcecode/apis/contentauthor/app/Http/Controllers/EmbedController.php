<?php

namespace App\Http\Controllers;

use App\ACL\ArticleAccess;
use App\H5pLti;
use App\Http\Libraries\EmbedlyService;
use App\Http\Libraries\License;
use App\Http\Libraries\LtiTrait;
use App\Http\Requests\EmbedRequest;
use App\Libraries\DataObjects\EmbedStateDataObject;
use App\Link;
use App\LinksExternaldata;
use App\Traits\ReturnToCore;
use Cerpus\LicenseClient\Contracts\LicenseContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class EmbedController extends Controller
{
    use LtiTrait;
    use ReturnToCore;
    use ArticleAccess;

    protected $lti;
    protected $licenseClient;

    public function __construct(H5pLti $h5pLti, LicenseContract $licenseClient)
    {
        $this->middleware('core.return', ['only' => ['create']]);
        $this->middleware('core.auth', ['only' => ['create', 'edit', 'store', 'update']]);
        $this->middleware('core.locale', ['only' => ['create', 'edit', 'store', 'update']]);

        $this->lti = $h5pLti;
        $this->licenseClient = $licenseClient;
    }

    public function create(Request $request)
    {
        if (!$this->canCreate()) {
            abort(403);
        }

        $licenseLib = app(License::class);  //new License(config('license'), config('cerpusauth.user'), config('cerpusauth.secret'));
        $ltiRequest = $this->lti->getLtiRequest();

        $licenses = $licenseLib->getLicenses($ltiRequest);
        $license = $licenseLib->getDefaultLicense($ltiRequest);
        $emails = '';
        $link = app(Link::class);
        $redirectToken = $request->get('redirectToken');
        $useDraft = false;
        $canPublish = true;
        $canList = true;

        $state = EmbedStateDataObject::create([
            'license' => $license,
            'redirectToken' => $request->get('redirectToken'),
            'route' => route('embed.store'),
            '_method' => "POST",
        ])->toJson();

        return view('embed.create')->with(compact(['licenses', 'license', 'emails', 'link', 'redirectToken', 'useDraft', 'canPublish', 'canList', 'state']));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param EmbedRequest $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(EmbedRequest $request)
    {
        if (!$this->canCreate()) {
            abort(403);
        }
        $inputs = $request->all();
        $url = $inputs['link'];

        $response = EmbedlyService::get($url);
        if ($response == null) {
            throw ValidationException::withMessages([
                "link" => "not found"
            ]);
        }

        $urlToCore = $this->getCoreBaseUrl($request->get('redirectToken'));

        $responseValues = [
            'url' => $urlToCore . "?" . http_build_query([
                    "return_type" => "link",
                    "url" => $inputs['link'],
                ])
        ];

        return response()->json($responseValues, Response::HTTP_CREATED);
    }
}
