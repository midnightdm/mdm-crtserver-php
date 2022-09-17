
<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
/* * * * * * * * *
 * Location Class
 * src/Zone.class.php
 * 
 * 
 */

class ZONE {
    //In each array 0 is printed, 1 is spoken
    public static $m465 = ["at M-465, 3 mi below Monpelier, Iowa", "at marker 465, 3 miles below Montpelier, Iowa"];
    public static $m466 = ["at M-466, 2 mi below Monpelier, Iowa", "at marker 466, two miles below Montpelier, Iowa"];
    public static $m467 = ["at M-467, 1 mi below Monpelier, Iowa", "at marker 467, one mile below Montpelier, Iowa"];
    public static $m468 = ["at M-468 by Montpelier, Iowa", "at marker 468 by Montpelier, Iowa"];
    public static $m469 = ["at M-469 by the Cargill Salt dock", "at marker 469 by the Cargill Salt dock"];
    public static $m470 = ["at M-470 2 mi below Buffalo, Iowa", "at marker 470, two miles below Buffalo, Iowa"];
    public static $m471 = ["at M-471 1 mi below Buffalo, Iowa", "at marker 471, one mile below Buffalo, Iowa"];
    public static $m472 = ["at M-472 in Buffalo, Iowa", "at marker 472 in Buffalo, Iowa"];
    public static $m473 = ["at M-473 in Buffalo, Iowa", "at marker 473 in Buffalo, Iowa"];
    public static $m474 = ["at M-474, 4 mi below the I-280 bridge", "at marker 474, 4 miles below the Interstate 280 bridge"];
    public static $m475 = ["at M-475 by Linwood Mining", "at marker 475 by Linwood Mining"];
    public static $m476 = ["at M-476 by Harvest States Co-op", "at marker 476 by Harvest States Co-op"];
    public static $m477 = ["at M-477 by Horse Island", "at marker 477 by Horse Island"];
    public static $m478 = ["at M-478 near the I-280 bridge", "at marker 478 near the Interstate 280 bridge"];
    public static $m479 = ["at M-479 by the Rock River junction", "at marker 479 by the Rock River junction"];
    public static $m480 = ["at M-480 by Credit Island Lodge", "at marker 480 by Credit Island Lodge"];
    public static $m481 = ["at M-481 near the Nestle-Purina plant", "at marker 481 near the Nestle-Purina plant"];
    public static $m482 = ["at M-482 between Lock 15 & Centennial Bridge", "at marker 482 between Lock 15 and the Centennial Bridge"];
    public static $m483 = ["at M-483 <1 mi above Lock 15", "at marker 483 less than 1 mile above Lock 15"];
    public static $m484 = ["at M-484 by Lindsay Park Yacht Club", "at marker 484 by Davenport's Lindsay Park Yacht Club"];
    public static $m485 = ["at M-485 below I-74 bridge", "at marker 485 just below the I-74 bridge"];
    public static $m486 = ["at M-486 by I-74 bridge Bettendorf", "at marker 486 by the I-74 bridge in Bettendorf"];
    public static $m487 = ["at M-487 Bettendorf 6.5 miles below Lock 14", "at marker 487 in Bettendorf, 6.5 miles below Lock 14"];
    public static $m488 = ["at M-488 Bettendorf 5.5 miles below Lock 14", "at marker 488 in Bettendorf, 5.5 miles below Lock 14"];
    public static $m489 = ["at M-489 by Arconic plant 4.5 miles below Lock 14","at marker 489 by the Arconic plant, 4.5 miles below Lock 14"];
    public static $m490 = ["at marker 490 3.5 miles below Lock 14","at marker 490, 3.5 miles below Lock 14",];
    public static $m491 = ["at marker 491 2.5 miles below Lock 14","at marker 491, 2.5 miles below Lock 14"];
    public static $m492 = ["at M-492 Hampton, IL 1.5 miles below Lock 14", "at marker 492 in Hampton, 1.5 miles below Lock 14"];
    public static $m493 = ["at marker 493 half mile below Lock 14","at marker 493, a half mile below Lock 14"];
    public static $m494 = ["at marker 494 half mile above Lock 14","at marker 494, a half mile above Lock 14"];
    public static $m495 = ["at marker 495 1.5 miles above Lock 14","at marker 495, 1.5 miles above Lock 14"];
    public static $m496 = ["at marker 496 2.5 miles above Lock 14","at marker 496, 2.5 miles above Lock 14"];
    public static $m497 = ["at marker 497 3.5 miles above Lock 14","at marker 497, 3.5 miles above Lock 14"];
    public static $m498 = ["at marker 498 4.5 miles above Lock 14","at marker 498, 4.5 miles above Lock 14"];
    public static $m499 = ["at marker 499 5.5 miles above Lock 14","at marker 499, 5.5 miles above Lock 14"];
    public static $m500 = ["at marker 500 6.5 miles above Lock 14","at marker 500, 6.5 miles above Lock 14"];
    public static $m501 = ["at marker 501 1 mile below Princeton, IA","at marker 501, 1 mile below Princeton, Iowa"];
    public static $m502 = ["at marker 502 by Princeton, IA","at marker 502 near Princeton, Iowa",];
    public static $m503 = ["at marker 503 by Princeton, IA","at marker 503 near Princeton, Iowa"];
    public static $m504 = ["at marker 504 by Cordova, IL", "at marker 504 near Cordova"];
    public static $m505 = ["at marker 505 1 mile below QC nuke plant", "at marker 505, 1 mile below the Quad Cities nuclear plant"];
    public static $m506 = ["at marker 506 by QC nuke plant","at marker 506 near the Quad Cities nuclear plant"];
    public static $m507 = ["at marker 507 by QC nuke plant","at marker 507 above the Quad Cities nuclear plant"];
    public static $m508 = ["at marker 508 1 mile above QC nuke plant","at marker 508, 1 mile above the Quad Cities nuclear plant"];
    public static $m509 = ["at marker 509 by 3M plant 9 miles below Clinton drawbridge","at marker 509 near 3M, 9 miles below the Clinton drawbridge"];
    public static $m510 = ["at mile marker 510 8 miles below Clinton drawbridge", "at mile marker 510, 8 miles below the Clinton drawbridge"];
    public static $malbany = ["at Albany sandpit backwaters","in the Albany sandpit back-waters"];
    public static $m511 = ["at marker 511 by Camanche 7 miles below Clinton drawbridge","at marker 511 by Kamanch, 7 miles below the Clinton drawbridge"];
    public static $m512 = ["at M-512 by Camanche marina 6 miles below Clinton drawbridge", "at marker 512 by Kamanch marina, 6 miles below Clinton drawbridge"];
    public static $mcamanche = ["In Camanche Harbor", "in Kamanch Harbor"];
    public static $m513 = ["at M-513 Albany, IL 5 miles below Clinton drawbridge", "at marker 513 by Albany, 5 miles below the Clinton drawbridge"];
    public static $m514 = ["at M-514 Albany, IL 4 miles below Clinton drawbridge","at marker 514 by Albany, 4 miles below the Clinton drawbridge"];
    public static $m515 = ["at M-515 by Vans Landing 3 miles below Clinton drawbridge","at marker 515 by Vans Landing 3 miles below the Clinton drawbridge"];
    public static $m516 = ["at marker 516 2 miles below Clinton drawbridge","at marker 516, 2 miles below the Clinton drawbridge"];
    public static $m517 = ["at marker 517 1 mile below Clinton drawbridge","at marker 517, 1 mile below the Clinton drawbridge"];
    public static $mbeaver = ["In Beaver Slough, Clinton's industrial district", "in Beaver Slough, Clinton's industrial district"];
    public static $m518 = ["at marker 518, Clinton drawbridge", "at marker 518, Clinton's historic railroad drawbridge"];
    public static $m519 = ["at marker 519 near Clinton Marina","at marker 519 near the Clinton Marina"];
    public statis $mjoyce_slough_clinton = ["at Joyce Slough in Clinton", "at Joyce Slough, home of Clinton Marina"]
    public static $m520 = ["at marker 520, Clinton's North bridge","at marker 520, Clinton's North bridge"];
    public static $m521 = ["at marker 521 1 mile above Clinton's North bridge","at marker 521, 1 mile above Clinton's North bridge"];
    public static $m522 = ["at marker 522 half mile below Lock 13","at marker 522 a half mile below Lock 13"];
    public static $m523 = ["at marker 523 half mile above Lock 13","at marker 523 a half mile above Lock 13"];
    public static $m524 = ["at marker 524 1.5 miles above Lock 13","at marker 524, 1.5 miles above Lock 13"];
    public static $m525 = ["at marker 525 2.5 miles above Lock 13","at marker 525, 2.5 miles above Lock 13"];
    public static $m526 = ["at marker 526 3.5 miles above Lock 13","at marker 526, 3.5 miles above Lock 13"];
    public static $m527 = ["at marker 527 4.5 miles above Lock 13","at marker 527, 4.5 miles above Lock 13"];
    public static $m528 = ["at marker 528 5.5 miles above Lock 13","at marker 528, 5.5 miles above Lock 13"];
    public static $m529 = ["at marker 529 6.5 miles above Lock 13","at marker 529, 6.5 miles above Lock 13"];
    public static $m530 = ["at marker 530 7.5 miles above Lock 13","at marker 530, 7.5 miles above Lock 13"];
    public static $m531 = ["at marker 531 8.5 miles above Lock 13","at marker 531, 8.5 miles above Lock 13"];
    public static $m532 = ["at mile 532, 9.5 abv Lock 13, 3 blw Sabula drawbridge","at mile 532, 9.5 miles above Lock 13 and 3 miles below the Sabula drawbridge"];
    public static $m533 = ["at mile 533, 10.5 abv Lock 13, 2 blw Sabula drawbridge","at mile 533, 10.5 miles above Lock 13 and 2 miles below the Sabula drawbridge"];
    public static $m534 = ["at marker 534 1 mile below Sabula drawbridge","at marker 534, 1 mile below the Sabula drawbridge"];
    public static $msabula = ["In Island City Harbor at Sabula", "in Island City Harbor at Sabula"];
    public static $m535 = ["at marker 535 Sabula drawbridge","at marker 535, the Sabula drawbridge"];
    public static $m536 = ["at marker 536 1 mile above Sabula drawbridge","at marker 536, 1 mile above the Sabula drawbridge"];
    public static $m537 = ["at Savanna, IL 2 miles above Sabula drawbridge","at Savanna Illinois, 2 miles above the Sabula drawbridge"];
    public static $m538 = ["at marker 538 3 miles above Sabula drawbridge","at marker 538, 3 miles above the Sabula drawbridge"];
    public static $m539 = ["at marker 539 4 miles above Sabula drawbridge","at marker 539, 4 miles above the Sabula drawbridge"];
    public static $m540 = ["at marker 540 5 miles above Sabula drawbridge","at marker 540, 5 miles above the Sabula drawbridge"];
    public static $malpha = ["3 miles N of Lock 13", "3 miles north of Lock 13"];
    public static $mbravo = ["at Lock 13, Fulton", "at Lock 13 by Fulton, Illinois"];
    public static $mcharlie = ["at RR drawbridge, Clinton", "at Clinton's railroad drawbridge"];
    public static $mdelta = ["3 miles S of RR drawbridge","3 miles south of Clinton's railroad drawbridge"];
    public static $mecho = ["at the I-80 bridge, LeClaire", "at the interstate 80 bridge in LeClaire"];
    public static $mfoxtrot = ["at Lock 14, Princeton","at Lock 14 by Princeton, Iowa"];
    public static $mgolf = ["at Lock 15, Davenport", "at Lock 15 in Davenport"];
    public static $mhotel = ["at I-280 bridge, Davenport", "at the Interstate two-eighty bridge in Davenport"];
    public static $mlakepotter = ["at Sunset Marina, Rock Island", "at Lake Potter, the home of Sunset Marina in Rock Island"];
    public static $mcredit_island_slough = ["in Davenport Harbor", "at Davenport Harbor in Credit Island Slough"];

}
