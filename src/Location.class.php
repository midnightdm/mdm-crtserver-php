<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
/* * * * * * * * *
 * Location Class
 * daemon/src/Location.class.php
 * 
 * Includes Location, Zone and Point classes 
 */

class Location {
  public $live;
  public $mm;
  public $lastMM;
  public $description;
  public $point;
  public $event;
  public $events; //array
  public $eventTS; 
  public $lastEvent;
  public $lastEventTS;


  public function __construct($livescan) {
    $this->live = $livescan; //Callback
    $this->mm   = "new";
    $this->event = "new";
    $this->events = [];
  }

  public function setPoint() {
    $this->point = [$this->live->liveLastLon, $this->live->liveLastLat];
  }

  public function calculate($suppressTrigger=false) {
    flog( "Location::calculate()...\n");
    //Define points of polygons represenating geographic zones


    //482=>[[-90.58403075588232,41.51763670388024],[-90.58113556831556,41.51200304813109],[-90.55951551882352,41.51032618297966],[-90.56692264476122,41.52129897352564]],
    //483=>[[-90.56692264476122,41.52129897352564],[-90.55951551882352,41.51032618297966],[-90.54552841799722,41.50964503103194],[-90.54808920603612,41.52939278853504]],

    $polys = [
      465=>[[-90.87394086670139,41.44768419634712],[-90.86125193962113,41.43255666090475],[-90.85296756533776,41.43482091056742],[-90.85686544012165,41.45571761713665]],
      466=>[[-90.85686544012165,41.45571761713665],[-90.85296756533776,41.43482091056742],[-90.83850707891371,41.43775316892549],[-90.83612580206753,41.45842029555808]],
      467=>[[-90.83612580206753,41.45842029555808],[-90.83850707891371,41.43775316892549],[-90.81910778473805,41.44023906213188],[-90.81579578160753,41.45812364409113]],
      468=>[[-90.81579578160753,41.45812364409113],[-90.81910778473805,41.44023906213188],[-90.79970003179358,41.44135211519289],[-90.79677900949125,41.45821571235599]],
      469=>[[-90.79677900949125,41.45821571235599],[-90.79970003179358,41.44135211519289],[-90.78295723318516,41.43937988586635],[-90.77923021773982,41.45480028536314]],
      470=>[[-90.77923021773982,41.45480028536314],[-90.78295723318516,41.43937988586635],[-90.76228125151724,41.43848056222637],[-90.75857797069449,41.45526673208312]],
      471=>[[-90.75857797069449,41.45526673208312],[-90.76228125151724,41.43848056222637],[-90.74155812873529,41.43841293014096],[-90.74129986697304,41.45392873207133]],
      472=>[[-90.74129986697304,41.45392873207133],[-90.74155812873529,41.43841293014096],[-90.72160661849664,41.44463759905712],[-90.72410622464567,41.45527364822565]],
      473=>[[-90.72410622464567,41.45527364822565],[-90.72160661849664,41.44463759905712],[-90.70364472745545,41.446415668045],[-90.7041274333198,41.45786933125446]],
      474=>[[-90.7041274333198,41.45786933125446],[-90.70364472745545,41.446415668045],[-90.68031730188827,41.44567946835805],[-90.68759802242619,41.46032658481339]],
      475=>[[-90.68759802242619,41.46032658481339],[-90.68031730188827,41.44567946835805],[-90.66699979606632,41.45681519842793],[-90.66702910960484,41.46514508917074]],
      476=>[[-90.66702910960484,41.46514508917074],[-90.66699979606632,41.45681519842793],[-90.63344843995473,41.45221935165051],[-90.65657718563965,41.46949488984797]],
      477=>[[-90.65657718563965,41.46949488984797],[-90.63344843995473,41.45221935165051],[-90.63509744573608,41.47303945911423],[-90.64034645199828,41.4774154619168]],
      478=>[[-90.64034645199828,41.4774154619168],[-90.63509744573608,41.47303945911423],[-90.617486830184,41.48088370542879],[-90.62425550599895,41.48662487870652]],
      479=>[[-90.62425550599895,41.48662487870652],[-90.617486830184,41.48088370542879],[-90.60404311701011,41.49180558200741],[-90.61073943780512,41.49459057955315]],
      480=>[[-90.61073943780512,41.49459057955315],[-90.60404311701011,41.49180558200741],[-90.59755934315619,41.50443881426396],[-90.60571003154891,41.51055979390778]],
      481=>[[-90.60571003154891,41.51055979390778],[-90.59755934315619,41.50443881426396],[-90.58113556831556,41.51200304813109],[-90.58403075588232,41.51763670388024]],
      482=>[[-90.5811256857077,41.51201485900806],[-90.57633351284976,41.5130259276031],[-90.56606024159272,41.51110682661161],[-90.55953411819094,41.51032368080836],[-90.56453103223885,41.51773505871055],[-90.56737615759825,41.51607732772189],[-90.57120149135591,41.5197672665899],[-90.58399811597744,41.51757650478604],[-90.5811256857077,41.51201485900806]],
      483=>[[-90.56690578029767,41.52120571329969],[-90.56506525237579,41.51856761126993],[-90.56213678265219,41.51984969809591],[-90.56160589489959,41.5193919806723],[-90.56451209561448,41.51774150477866],[-90.55969018213969,41.51059792472956],[-90.54554576608521,41.50964638997059],[-90.54810260646045,41.52947278977826],[-90.55724602488634,41.52657412554857],[-90.56690578029767,41.52120571329969]],    
      484=>[[-90.54808920603612,41.52939278853504],[-90.54552841799722,41.50964503103194],[-90.53372071448936,41.51035784153727],[-90.52892484428197,41.52601736034169]],
      485=>[[-90.52892484428197,41.52601736034169],[-90.53372071448936,41.51035784153727],[-90.5092203536731,41.51372097487243],[-90.50971806363766,41.52215220467504]],
      486=>[[-90.50971806363766,41.52215220467504],[-90.5092203536731,41.51372097487243],[-90.48856266269104,41.5145424556308],[-90.48875678287305,41.52140202400295]],
      487=>[[-90.48875678287305,41.52140202400295],[-90.48856266269104,41.5145424556308],[-90.47036467716465,41.51537456609466],[-90.47251555885472,41.52437816051497]],
      488=>[[-90.47251555885472,41.52437816051497],[-90.47036467716465,41.51537456609466],[-90.45000250745086,41.52480546208061],[-90.45698288389242,41.53057735758976]],
      489=>[[-90.45698288389242,41.53057735758976],[-90.45000250745086,41.52480546208061],[-90.43804967962095,41.53668343008653],[-90.4461928429114,41.54182560886835]],
      490=>[[-90.4461928429114,41.54182560886835],[-90.43804967962095,41.53668343008653],[-90.42465891516093,41.54714647168962],[-90.43225148614556,41.55492191671779]],
      491=>[[-90.43225148614556,41.55492191671779],[-90.42465891516093,41.54714647168962],[-90.41359632007243,41.55879211219473],[-90.42215634673808,41.56423876538352]],
      492=>[[-90.42215634673808,41.56423876538352],[-90.41359632007243,41.55879211219473],[-90.40121765684347,41.56578132917156],[-90.40755589318907,41.57200066107595]],
      493=>[[-90.40755589318907,41.57200066107595],[-90.40121765684347,41.56578132917156],[-90.38766103940617,41.57132529050489],[-90.39384285792221,41.57842796885789]],
      494=>[[-90.39384285792221,41.57842796885789],[-90.38766103940617,41.57132529050489],[-90.37097459099577,41.57455780093269],[-90.37455561078977,41.58171517893158]],
      495=>[[-90.37455561078977,41.58171517893158],[-90.37097459099577,41.57455780093269],[-90.3498980145362,41.58193114855811],[-90.35418070340366,41.5875726488084]],
      496=>[[-90.35418070340366,41.5875726488084],[-90.3498980145362,41.58193114855811],[-90.33608085417411,41.59502112101575],[-90.34328730016247,41.59576427084198]],
      497=>[[-90.34328730016247,41.59576427084198],[-90.33608085417411,41.59502112101575],[-90.33646143861851,41.6111032102589],[-90.34404272829823,41.61119012348694]],
      498=>[[-90.34404272829823,41.61119012348694],[-90.33646143861851,41.6111032102589],[-90.33663122233754,41.62387063319586],[-90.3472745860646,41.62454773858045]],
      499=>[[-90.3472745860646,41.62454773858045],[-90.33663122233754,41.62387063319586],[-90.3381794138162,41.63955239006518],[-90.3480736269221,41.63971945269969]],

      "lakepotter"=>[[-90.60291821936136,41.49354089561368],[-90.60515330401103,41.48873653233955],[-90.60835728780469,41.48558344711035],[-90.6041038102251,41.48389113738474],[-90.60037739863891,41.48436362602704],[-90.5969213240148,41.48903959660949],[-90.59657901636372,41.4922469460371],[-90.60291821936136,41.49354089561368]],

      "credit_island_slough"=>[[-90.63134741941096,41.48485323562083],[-90.62120203043624,41.48797893652774],[-90.61548073260025,41.49230296379835],[-90.60887864874843,41.50279539867355],[-90.61216663721751,41.50665860975543],[-90.61909167271669,41.50337113210738],[-90.63134741941096,41.48485323562083]],

      "echo"=>[[-90.3629940774167,41.57612132335647],[-90.36217706847489,41.57632071175892],[-90.36661112456599,41.58337050916753],[-90.36715827747643,41.58323092264241],[-90.3629940774167,41.57612132335647]],

      "foxtrot"=>[[-90.40234831475011,41.57399148505885],[ -90.40192049550757,41.57352528493669],[ -90.40009228575911,41.57424706811361],[ -90.39527617109974,41.56831545610599],[ -90.39443749164592,41.56860912427832],[-90.39950148227493,41.57443709497309],[-90.39712058888212,41.57532498004248],[-90.39793087734567,41.57595248689903],[-90.40234831475011,41.57399148505885]],

      "golf"=>[[-90.57119718322605,41.5197727920422],[ -90.5673840975264,41.51607446962929],[ -90.56162979231036,41.51941134974355],[ -90.56211369554093,41.51984963255459],[ -90.56609663118097,41.51809415925702],[ -90.56796101611982,41.52079186696324],[ -90.57119718322605,41.5197727920422]],

      "hotel"=>[[-90.62941710558779,41.47628754238154],[ -90.62844683491804,41.4770208382132],[ -90.63455074455194,41.4814587920874],[ -90.63545057217139,41.48103548137976],[ -90.62941710558779,41.47628754238154]],

      500=>[[-90.3480736269221,  41.63971945269969],  [-90.33817941381621,41.63955239006518], [-90.33649979303949, 41.65484790099703],  [-90.34380831321272, 41.65683003496228]  ],
      501=>[[-90.34380831321272, 41.65683003496228],  [-90.33649979303949,41.65484790099703], [-90.3286147300638,  41.66790001449647],  [-90.33988256792307, 41.66828476005874]  ],
      502=>[[-90.33988256792307, 41.66828476005874],  [-90.3286147300638,41.66790001449647],  [-90.32843393740198, 41.6798418644646],   [-90.33882199131011, 41.68036827724283]  ],
      503=>[[-90.33882199131011, 41.68036827724283],  [-90.32843393740198,41.6798418644646],  [-90.31540075610307, 41.68607027095535],  [-90.32382303252616, 41.69122269168967]  ],
      504=>[[-90.32382303252616, 41.69122269168967],  [-90.31540075610307,41.68607027095535], [-90.31077421309571, 41.70093421981962],  [-90.31560815565506, 41.70162133249737]  ],
      505=>[[-90.31560815565506, 41.70162133249737],  [-90.31077421309571,41.70093421981962], [-90.3144828164786,  41.71893714129034],  [-90.32324160813617, 41.71865527148766]  ],
      506=>[[-90.32324160813617, 41.71865527148766],  [-90.3144828164786,41.71893714129034],  [-90.31219715829357, 41.73209034176453],  [-90.32043157100178, 41.73305742526379]  ],
      507=>[[-90.32043157100178, 41.73305742526379],  [-90.31219715829357,41.73209034176453], [-90.30381674016407, 41.74473810650169],  [-90.30911551889101, 41.74805205206862]  ],
      508=>[[-90.30911551889101, 41.74805205206862],  [-90.30381674016407,41.74473810650169], [-90.29012440316585, 41.7570342469618],   [-90.29387889379554, 41.75940105234584]  ],
      509=>[[-90.29387889379554, 41.75940105234584],  [-90.29012440316585,41.7570342469618],  [-90.27848788377898, 41.76498972749543],  [-90.28216840054604, 41.76853414046849]  ],
      510=>[[-90.28216840054604, 41.76853414046849],  [-90.27848788377898,41.76498972749543], [-90.26200151315392, 41.770651247585950],  [-90.2654809443937,  41.77464017600214]  ],
      511=>[[-90.2654809443937,  41.77464017600214],  [-90.26200151315392,41.770651247585950],  [-90.24263626100766, 41.77880910965498], [-90.24800719074986, 41.7843434632554]  ],
      512=>[[-90.24800719074986, 41.7843434632554],   [-90.24263626100766,41.77880910965498], [-90.2284317808665,  41.78595112826723],  [-90.23473410036074, 41.79168622191222] ], 
      513=>[[-90.236808, 41.793668],  [-90.2284317808665,41.78595112826723],  [-90.21337944364016, 41.79404084443492],  [-90.2156953508097,  41.7973419581181]   ],
      514=>[[-90.2156953508097,  41.7973419581181],   [-90.21337944364016,41.79404084443492], [-90.19581674354208, 41.79898355228364],  [-90.19822143802581, 41.8025198609788]   ],
      515=>[[-90.19822143802581, 41.8025198609788],   [-90.19581674354208,41.79898355228364], [-90.17633565144088, 41.80648691881999],  [-90.18352536643455, 41.80932693789443]  ],
      516=>[[-90.18352536643455, 41.80932693789443],  [-90.17633565144088,41.80648691881999], [-90.18032482162711, 41.82393548957531],  [-90.18485994749022, 41.8234823269278]   ],
      517=>[[-90.18479951416624,41.82345405381364], [-90.18034156127018,41.82386697419022], [-90.17363844972714,41.83741256126471], [-90.18528711883474,41.83738488833541], [-90.18479951416624,41.82345405381364]  ],
      518=>[[-90.18498674961879,41.83745413025019], [-90.17426449496931,41.83754713555957], [-90.17186371262994,41.84544262688185], [-90.17279839780834,41.85034247434979], 
      [-90.17906452226369,41.85124828595436], [-90.18246236901253,41.84681288842535], [-90.18498674961879,41.83745413025019]    ],
      519=>[[-90.17908346056349, 41.8513020234478],   [-90.17295527825956,41.850379130804],   [-90.17058699252856, 41.86429560522607],  [-90.17610039282224, 41.86515500754595]  ],
      520=>[[-90.17610039282224, 41.86515500754595],  [-90.17058699252856,41.86429560522607], [-90.16660198044828, 41.8760873927711],   [-90.17297767304423, 41.87737306056449]  ],
      521=>[[-90.17297767304423, 41.87737306056449],  [-90.16660198044828,41.8760873927711],  [-90.15871961546813, 41.88892630366035],   [-90.16238975538499, 41.89065244219969]  ],
      522=>[[-90.16238975538499, 41.89065244219969], [-90.15871961546813,41.88892630366035], [-90.151909, 41.903010], [-90.167240, 41.903965]],
      523=>[[-90.167240, 41.903965], [-90.151909, 41.903010], [-90.147937, 41.916824], [-90.164227, 41.916904]],
      524=>[[-90.164227, 41.916904], [-90.147937, 41.916824], [-90.150351, 41.928544], [-90.178143, 41.928487]],
      525=>[[-90.178143, 41.928487], [-90.150351, 41.928544], [-90.154714, 41.940933], [-90.170325, 41.941093]],
      526=>[[-90.170325, 41.941093], [-90.154714, 41.940933], [-90.154570, 41.955656], [-90.169716, 41.956191]],
      527=>[[-90.169716, 41.956191], [-90.154570, 41.955656], [-90.150207, 41.966624], [-90.165536, 41.971451]],
      528=>[[-90.165536, 41.971451], [-90.150207, 41.966624], [-90.128595, 41.983866], [-90.160597, 41.983653]],
      529=>[[-90.160597, 41.983653], [-90.128595, 41.983866], [-90.129745, 41.998937], [-90.159155, 41.998832]],
      530=>[[-90.159155, 41.998832], [-90.129745, 41.998937], [-90.126528, 42.012881], [-90.160529, 42.010256]],
      531=>[[-90.160529, 42.010256], [-90.126528, 42.012881], [-90.136450, 42.027552], [-90.162878, 42.025086]],
      532=>[[-90.162878, 42.025086], [-90.136450, 42.027552], [-90.15151752534508, 42.03730169372241],  [-90.16063756667363, 42.03651491321578]],  
      533=>[[-90.16063756667363, 42.03651491321578],  [-90.15151752534508,42.03730169372241], [-90.16066649304122, 42.04930465441836],  [-90.16890457045166, 42.04885717910146]  ],
      534=>[[-90.16890457045166, 42.04885717910146],  [-90.16066649304122,42.04930465441836], [-90.16266001168944, 42.06507225175709],  [-90.16873927252988, 42.06458933574678]  ],
      535=>[[-90.16873927252988, 42.06458933574678],  [-90.16266001168944,42.06507225175709], [-90.16249823994366, 42.07970814767357],  [-90.16914609409496, 42.0804515612181],   ],
      536=>[[-90.16914609409496, 42.0804515612181],   [-90.16249823994366,42.07970814767357], [-90.1579947493362,  42.09136054497117],  [-90.16729803875997, 42.09221812981502]  ],
      537=>[[-90.16729803875997, 42.09221812981502],  [-90.1579947493362,42.09136054497117],  [-90.15894760458957, 42.10600456364353],  [-90.16382083849952, 42.10622273166468]  ],
      538=>[[-90.16382083849952, 42.10622273166468],  [-90.15894760458957,42.10600456364353], [-90.16024166340684, 42.12179322620005],  [-90.16773051913361, 42.11833177709393]  ],
      539=>[[-90.16773051913361, 42.11833177709393],  [-90.16024166340684,42.12179322620005], [-90.18304430150994, 42.12795599576975],  [-90.18197341024099, 42.12474496670414]  ],

      "albany"=>[[-90.27515035952995,41.75674530469114],  [-90.25720788319468,41.76494014559641],  [-90.25782420656908,41.76892319694783],  [-90.2681668076286,41.76756818218026], [-90.27837794161196,41.7647960627926],  [-90.27515035952995,41.75674530469114] ],


      "camanche"=>[[-90.24411274997723,41.7867227592737],[-90.24052772077873,41.79191920668919],[-90.24483821902118,41.79315328700208],[-90.24866415680138,41.78655999605407],[-90.24948281326756,41.78435425727831],[-90.24892321653469,41.78381433943699],[-90.24411274997723,41.7867227592737]],

      "beaver"=> [[-90.23294839981214, 41.79808538536009], [-90.22701749141592,41.80756903332713], [-90.21559749792131, 41.81230821223932], [-90.20557127592812, 41.81802061899466], [-90.19441158230757, 41.82295207417607], [-90.1852557317826, 41.82723424579132], [-90.1864738578501, 41.82944337432793], [-90.19083424544831, 41.82723719598464], [-90.19632708046788, 41.82542118488396], [-90.20330171463473, 41.82315092259923], [-90.21080006723422, 41.81912672727466], [-90.21638017690674, 41.81568600506505], [-90.22631847832557, 41.81192101655164], [-90.23652024451495, 41.80341254671695], [-90.23852907095876, 41.79282295356613], [-90.2343452764614, 41.79288804500343], [-90.23294839981214, 41.79808538536009]],

      "sabula"=> [[-90.16917288193625,42.06032208154448],[-90.16901335882697,42.06274636281189],[-90.17070309171807,42.06250890705313],[-90.17166206922242,42.0620511009892],[-90.17118231549374,42.06072880609142],[-90.17220933933675,42.05793152988566],[-90.1706795005182,42.05769432506564],[-90.16917288193625,42.06032208154448] ],

      "alpha"=>[[-90.18175807981993,41.93844045624406],[-90.10727770274339,41.93882767095748],[-90.10758290312427,41.94061272536801],[-90.18173172439252,41.94109215813975],[-90.18175807981993,41.93844045624406]],

      "bravo"=>[[-90.17384402924193,41.89723456537121],[-90.16139831396862,41.89740471269587],[-90.15436694723829,41.89574701405598],[-90.1527173408855,41.89887716376366],[-90.16033335309578,41.89951309880558],[-90.16894635958988,41.89940369517984],[-90.17320673261528,41.89797039505211],[-90.17384402924193,41.89723456537121]],

      "charlie"=>[[-90.18691306733906,41.83569685872726],[-90.16888090651308,41.8359038974395],[-90.16905537691486,41.83688562511055],[-90.18689095019816,41.83662425531738],[-90.18691306733906,41.83569685872726]],

      "delta"=>[[-90.21429864522167,41.79989690763232],[-90.18786002625238,41.79993804296918],[-90.18791890792576,41.80114586913619],[-90.21418401179969,41.80113158192712],[-90.21429864522167,41.79989690763232]],

    ];
            
    $urangec = [500,501,502,503,504,505,506,507,508,509,510,'albany',511,512,'camanche',513,514,'delta',515,516,517, 'beaver','charlie', 518,519,520,521,522,'bravo',523,524,525,'alpha',526,527,528,529,530,531,532,533,534,'sabula',535,536,537,538,539];
    $drangec = [539,538,537,536,535,'sabula',534,533,532,531,530,529,528,527,526,'alpha',525,524,523,'bravo',522,521,520,519,518,'charlie','beaver', 517,516,515,'delta',514,513,'camanche',512,511,'albany',510,509,508,507,506,505,504,503,502,501,500,499,498,497,496,495,'echo','foxtrot','golf','hotel'];
    $urangeq = ['echo','foxtrot','lakepotter','credit_island_slough','golf','hotel',465,466,467,468,469,470,471,472,473,474,475,476,477,478,479,480,481,482,483,484,485,486,487,488,489,490,491,492,493,494,495,496,497,498,499];
    $drangeq = ['hotel','golf','lakepotter','credit_island_slough','foxtrot','echo',499,498,497,496,495,494,493,492,491,490,489,488,487,486,485,484,483,482,481,480,479,478,477,476,475,474,473,472,471,470,469,468,467,466,465];
    
    $this->setPoint();
    $region = $this->determineRegion();
    //Set range by region and direction
    switch($region) {
      case "clinton": $rg = "c"; break;
      case "qc"     : $rg = "q"; break;
      case "outside": continue;
    }
    $rangeName  = $this->live->liveDirection=="upriver" ? "urange".$rg : "drange".$rg;
    flog("            ...range is $rangeName\n");
    $range = $$rangeName;

    foreach($range as $m) {
      $inside = $this->insidePoly($this->point, $polys[$m]);   
      if($inside) {
        $this->lastEvent   = $this->event;   //Save last event  before updating
        $this->lastEventTS = $this->eventTS; //Save time of last event update
        //Use location data to build event code
        if($this->live->liveDirection=="upriver") {
          $dir = "u";   $um  = $m;
        } else {
          $dir = "d";   
          $um = is_int($m) ? ($m + 1) : $m; //Shows polygon entry at upper mile line for downriver movement
        }
        $mileMarker = "m".$m;
        //desciption is an array containing [0] short text for status and [1] longer text for speech conversion
        $this->description = ZONE::$$mileMarker;
        $eventTS = time();
        $waypoint = false;
        $type  = strpos($this->live->liveVessel->vesselType, "assenger") ? "p" : "a";
        if(is_int($m)) { //Numbered mile marker zones
          $event = "m".$m.$dir.$type;          
        } else {         //Named zones          
          //Concatanate event
          switch($m) {
            case "beaver": 
              $event = "beaver".$dir."a";  break;
            case "camanche":
              $event = "camanche";      break;
            case "albany":
              $event = "albany";        break;
            case "sabula":
              $event = "sabula";        break;
            case "alpha":
              $waypoint = true;         $wpName = "Alpha";
              $event = "alpha".$dir.$type;  break;
            case "bravo":
              $waypoint = true;         $wpName = "Bravo";
              $event = "bravo".$dir.$type;  break;
            case "charlie":
              $waypoint = true;         $wpName = "Charlie";
              $event = "charlie".$dir.$type;  break;
            case "delta":
              $waypoint = true;         $wpName = "Delta";
              $event = "delta".$dir.$type;  break;
            case "echo":
              $waypoint = true;         $wpName = "Echo";
              $event = "echo".$dir.$type;  break;
            case "foxtrot":
              $waypoint = true;         $wpName = "Foxtrot";
              $event = "foxtrot".$dir.$type;  break;
            case "golf":
              $waypoint = true;         $wpName = "Golf";
              $event = "golf".$dir.$type;  break;
            case "hotel":
              $waypoint = true;         $wpName = "Hotel";
              $event = "hotel".$dir.$type;  break;            
          }           
        }
        if($this->verifyWaypointEvent($event, $suppressTrigger)) {
          if($waypoint) {
            $wsConcat = "liveMarker".$wpName."WasReached";
            $tsConcat = "liveMarker".$wpName."TS";
            $this->live->$wsConcat = true;
            $this->live->$tsConcat = $eventTS;
            flog("\33[42m      ...$wpName waypoint was reached by ".$this->live->liveName." traveling Upriver.\033[0m\n\n");
          } else {
            flog( "\033[43m   ...".$this->live->liveName." at ".$event.".\033[0m\n\n");
          }
          $this->live->callBack->AlertsModel->triggerEvent($this->event, $this->live);
        }
        break;        
      }  
    }
    if($inside==false) {
      $this->description = ["undetermined location", "at an as yet undetermined Location."];
      flog( "   ...search for ".$this->live->liveName." ended at $m \r\n");
    }
  }

  public function determineRegion() { //Returns "clinton" | "qc" | "outside"   
    $polys = [
      "clinton"=>[[-90.40382479466886,41.64720326378053],[-90.15589018585558,41.63905440118833],[-90.03647726615425,42.14497501389922],
      [-90.25330455033844,42.17020580505001],[-90.40382479466886,41.64720326378053]],
      "qc"=>[[-90.15918561340592,41.63731381803267],[-90.40375392359645,41.64698185704191],[-91.1443998108527,41.41024258877019],[-90.46229684338448,41.39866172374725],[-90.15918561340592,41.63731381803267]]
    ];
    $this->setPoint();
    if($this->insidePoly($this->point, $polys["clinton"])) {
      flog("Location::getCurrentRegion() = clinton \r\n");
      return "clinton";
    } else if($this->insidePoly($this->point, $polys["qc"])) {
      flog("Location::getCurrentRegion() = qc\r\n");
      return "qc";
    } else {
      flog("Location::getCurrentRegion() = \33[42m NOT IN REGION \033[0m\r\n");
      return "outside";
    }
  }

  public function insidePoly($point, $vs) {
      // ray-casting algorithm based on
      // http://www.ecse.rpi.edu/Homepages/wrf/Research/Short_Notes/pnpoly.html
  
      $x = $point[0]; $y = $point[1];
      $len = count($vs);
      $inside = false;
      for ($i = 0, $j = $len - 1; $i < $len; $j = $i++) {
          $xi = $vs[$i][0]; $yi = $vs[$i][1];
          $xj = $vs[$j][0]; $yj = $vs[$j][1];
  
          $intersect = (($yi > $y) != ($yj > $y))
              && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi);
          if ($intersect) { $inside = !$inside; }
      }
  
      return $inside;
  }

  public function verifyWaypointEvent($event, $supressTrigger=false) {
      flog( "   Location::verifyWaypointEvent()...\n");
      $status = $this->updateEventStatus($event, $supressTrigger);
      if($status) {
          //Push new event to array and do updates
          $this->lastEvent = $this->event;     
          $this->event = $event;
          $this->lastEventTS = $this->eventTS;
          $this->eventTS = time();
          $this->events[$this->event] = $this->eventTS; 
      }
      return $status;           
  }

  public function updateEventStatus($event, $suppressTrigger=false) {
      if($suppressTrigger) {
          flog( "\033[33m      ...Location::updateEventStatus() TRIGGER SUPPRESSED \033[0m\n");
          return false;
      }
      if($event == $this->lastEvent) {
          flog( "\033[33m      ...Location::updateEventStatus() SAME AS LAST EVENT\033[0m\n");
          return false;
      }
      //Is this event in array already?
      if(isset($this->events[$event])) {
          flog( "\033[33m      ...Location::updateEventStatus() EVENT IN ARRAY ALREADY\033[0m\n");
          return false;
      }
      //Reject update if one just happened
      if((time() - $this->lastEventTS) < 60) {
          flog( "\033[33m      ...Location::updateEventStatus() EVENT < 60 OLD \033[0m\n");
          return false;
      }
      flog( "\033[33m      ...Location::updateEventStatus($event) EVENT IS AUTHENTIC\033[0m\n");        
      return true;
  }
    
}