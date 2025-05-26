
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
    public static $m465 = ["at M-465, 3 mi below Monpelier, Iowa", "at marker 465, 3 miles below Montpelier, Iowa", 465];
    public static $m466 = ["at M-466, 2 mi below Monpelier, Iowa", "at marker 466, two miles below Montpelier, Iowa", 466];
    public static $m467 = ["at M-467, 1 mi below Monpelier, Iowa", "at marker 467, one mile below Montpelier, Iowa", 467];
    public static $m468 = ["at M-468 by Montpelier, Iowa", "at marker 468 by Montpelier, Iowa", 468];
    public static $m469 = ["at M-469 by the Cargill Salt dock", "at marker 469 by the Cargill Salt dock", 469];
    public static $m470 = ["at M-470 2 mi below Buffalo, Iowa", "at marker 470, two miles below Buffalo, Iowa", 470];
    public static $m471 = ["at M-471 1 mi below Buffalo, Iowa", "at marker 471, one mile below Buffalo, Iowa", 471];
    public static $m472 = ["at M-472 in Buffalo, Iowa", "at marker 472 in Buffalo, Iowa", 472];
    public static $m473 = ["at M-473 in Buffalo, Iowa", "at marker 473 in Buffalo, Iowa", 473];
    public static $m474 = ["at M-474, 4 mi below the I-280 bridge", "at marker 474, 4 miles below the Interstate 280 bridge", 474];
    public static $m475 = ["at M-475 by Linwood Mining", "at marker 475 by Linwood Mining", 475];
    public static $m476 = ["at M-476 by Harvest States Co-op", "at marker 476 by Harvest States Co-op", 476];
    public static $m477 = ["at M-477 by Horse Island", "at marker 477 by Horse Island", 477];
    public static $m478 = ["at M-478 near the I-280 bridge", "at marker 478 near the Interstate 280 bridge", 478];
    public static $m479 = ["at M-479 by the Rock River junction", "at marker 479 by the Rock River junction", 479];
    public static $m480 = ["at M-480 by Credit Island Lodge", "at marker 480 by Credit Island Lodge", 480];
    public static $m481 = ["at M-481 near the Nestle-Purina plant", "at marker 481 near the Nestle-Purina plant", 481];
    public static $m482 = ["at M-482 between Lock 15 & Centennial Bridge", "at marker 482 between Lock 15 and the Centennial Bridge", 482];
    public static $m483 = ["at M-483 <1 mi above Lock 15", "at marker 483 less than 1 mile above Lock 15", 483];
    public static $m484 = ["at M-484 by Lindsay Park Yacht Club", "at marker 484 by Davenport's Lindsay Park Yacht Club", 484];
    public static $m485 = ["at M-485 below I-74 bridge", "at marker 485 just below the I-74 bridge", 485];
    public static $m486 = ["at M-486 by I-74 bridge Bettendorf", "at marker 486 by the I-74 bridge in Bettendorf", 486];
    public static $m487 = ["at M-487 Bettendorf 6.5 miles below Lock 14", "at marker 487 in Bettendorf, 6.5 miles below Lock 14", 487];
    public static $m488 = ["at M-488 Bettendorf 5.5 miles below Lock 14", "at marker 488 in Bettendorf, 5.5 miles below Lock 14", 488];
    public static $m489 = ["at M-489 by Arconic plant 4.5 miles below Lock 14","at marker 489 by the Arconic plant, 4.5 miles below Lock 14", 489];
    public static $m490 = ["at marker 490 3.5 miles below Lock 14","at marker 490, 3.5 miles below Lock 14", 490];
    public static $m491 = ["at marker 491 2.5 miles below Lock 14","at marker 491, 2.5 miles below Lock 14", 491];
    public static $m492 = ["at M-492 Hampton, IL 1.5 miles below Lock 14", "at marker 492 in Hampton, 1.5 miles below Lock 14", 492];
    public static $m493 = ["at marker 493 half mile below Lock 14","at marker 493, a half mile below Lock 14", 493];
    public static $m494 = ["at marker 494 half mile above Lock 14","at marker 494, a half mile above Lock 14", 494];
    public static $mleclaire_slough = ["in LeClaire Slough by Lock 14", "in LeClaire Slough by Lock 14", 494];
    public static $m495 = ["at marker 495 1.5 miles above Lock 14","at marker 495, 1.5 miles above Lock 14", 495];
    public static $m496 = ["at marker 496 2.5 miles above Lock 14","at marker 496, 2.5 miles above Lock 14", 496];
    public static $m497 = ["at marker 497 3.5 miles above Lock 14","at marker 497, 3.5 miles above Lock 14", 497];
    public static $m498 = ["at marker 498 4.5 miles above Lock 14","at marker 498, 4.5 miles above Lock 14", 498];
    public static $m499 = ["at marker 499 5.5 miles above Lock 14","at marker 499, 5.5 miles above Lock 14", 499];
    public static $m500 = ["at marker 500 6.5 miles above Lock 14","at marker 500, 6.5 miles above Lock 14", 500];
    public static $m501 = ["at marker 501 1 mile below Princeton, IA","at marker 501, 1 mile below Princeton, Iowa", 501];
    public static $m502 = ["at marker 502 by Princeton, IA","at marker 502 near Princeton, Iowa", 502];
    public static $m503 = ["at marker 503 by Princeton, IA","at marker 503 near Princeton, Iowa", 503];
    public static $m504 = ["at marker 504 by Cordova, IL", "at marker 504 near Cordova", 504];
    public static $m505 = ["at marker 505 1 mile below QC nuke plant", "at marker 505, 1 mile below the Quad Cities nuclear plant", 505];
    public static $m506 = ["at marker 506 by QC nuke plant","at marker 506 near the Quad Cities nuclear plant", 506];
    public static $m507 = ["at marker 507 by QC nuke plant","at marker 507 above the Quad Cities nuclear plant", 507];
    public static $m508 = ["at marker 508 1 mile above QC nuke plant","at marker 508, 1 mile above the Quad Cities nuclear plant", 508];
    public static $m509 = ["at marker 509 by 3M plant 9 miles below Clinton drawbridge","at marker 509 near 3M, 9 miles below the Clinton drawbridge", 509];
    public static $m510 = ["at mile marker 510 8 miles below Clinton drawbridge", "at mile marker 510, 8 miles below the Clinton drawbridge", 510];
    public static $malbany = ["at Albany sandpit backwaters","in the Albany sandpit back-waters",510];
    public static $m511 = ["at marker 511 by Camanche 7 miles below Clinton drawbridge","at marker 511 by Kamanch, 7 miles below the Clinton drawbridge", 511];
    public static $m512 = ["at M-512 by Camanche marina 6 miles below Clinton drawbridge", "at marker 512 by Kamanch marina, 6 miles below Clinton drawbridge", 512];
    public static $mcamanche = ["In Camanche Harbor", "in Kamanch Harbor", 512];
    public static $m513 = ["at M-513 Albany, IL 5 miles below Clinton drawbridge", "at marker 513 by Albany, 5 miles below the Clinton drawbridge", 513];
    public static $m514 = ["at M-514 Albany, IL 4 miles below Clinton drawbridge","at marker 514 by Albany, 4 miles below the Clinton drawbridge", 514];
    public static $m515 = ["at M-515 by Vans Landing 3 miles below Clinton drawbridge","at marker 515 by Vans Landing 3 miles below the Clinton drawbridge", 515];
    public static $m516 = ["at marker 516 2 miles below Clinton drawbridge","at marker 516, 2 miles below the Clinton drawbridge", 516];
    public static $m517 = ["at marker 517 1 mile below Clinton drawbridge","at marker 517, 1 mile below the Clinton drawbridge", 517];
    public static $mbeaver = ["In Beaver Slough, Clinton's industrial district", "in Beaver Slough, Clinton's industrial district", 517];
    public static $m518 = ["at marker 518, Clinton drawbridge", "at marker 518, Clinton's historic railroad drawbridge", 518];
    public static $m519 = ["at marker 519 near Clinton Marina","at marker 519 near the Clinton Marina", 519];
    public static $mjoyce_slough_clinton = ["at Joyce Slough in Clinton", "at Joyce Slough, home of Clinton Marina", 519];
    public static $m520 = ["at marker 520, Clinton's North bridge","at marker 520, Clinton's North bridge", 520];
    public static $m521 = ["at marker 521 1 mile above Clinton's North bridge","at marker 521, 1 mile above Clinton's North bridge", 521];
    public static $m522 = ["at marker 522 half mile below Lock 13","at marker 522 a half mile below Lock 13",522];
    public static $m523 = ["at marker 523 half mile above Lock 13","at marker 523 a half mile above Lock 13",523];
    public static $m524 = ["at marker 524 1.5 miles above Lock 13","at marker 524, 1.5 miles above Lock 13", 524];
    public static $m525 = ["at marker 525 2.5 miles above Lock 13","at marker 525, 2.5 miles above Lock 13", 525];
    public static $m526 = ["at marker 526 3.5 miles above Lock 13","at marker 526, 3.5 miles above Lock 13", 526];
    public static $m527 = ["at marker 527 4.5 miles above Lock 13","at marker 527, 4.5 miles above Lock 13", 527];
    public static $m528 = ["at marker 528 5.5 miles above Lock 13","at marker 528, 5.5 miles above Lock 13", 528];
    public static $m529 = ["at marker 529 6.5 miles above Lock 13","at marker 529, 6.5 miles above Lock 13", 529];
    public static $m530 = ["at marker 530 7.5 miles above Lock 13","at marker 530, 7.5 miles above Lock 13", 530];
    public static $m531 = ["at marker 531 8.5 miles above Lock 13","at marker 531, 8.5 miles above Lock 13", 531];
    public static $m532 = ["at mile 532, 9.5 abv Lock 13, 3 blw Sabula drawbridge","at mile 532, 9.5 miles above Lock 13 and 3 miles below the sub Bula drawbridge", 532];
    public static $m533 = ["at mile 533, 10.5 abv Lock 13, 2 blw Sabula drawbridge","at mile 533, 10.5 miles above Lock 13 and 2 miles below the sub Bula drawbridge", 533];
    public static $m534 = ["at marker 534 1 mile below Sabula drawbridge","at marker 534, 1 mile below the sub Bula drawbridge", 534];
    public static $msabula = ["In Island City Harbor at Sabula", "in Island City Harbor at sub Bula", 534];
    public static $m535 = ["at marker 535 Sabula drawbridge","at marker 535, the sub Bula drawbridge", 535];
    public static $m536 = ["at marker 536 1 mile above Sabula drawbridge","at marker 536, 1 mile above the sub Bula drawbridge", 536];
    public static $m537 = ["at Savanna, IL 2 miles above Sabula drawbridge","at Savanna Illinois, 2 miles above the sub Bula drawbridge", 537];
    public static $m538 = ["at marker 538 3 miles above Sabula drawbridge","at marker 538, 3 miles above the sub Bula drawbridge", 538];
    public static $m539 = ["at marker 539 by Palisades state park lookout","at marker 539, near the Palisades state park lookout point", 539];
    public static $m540 = ["at marker 540 5 miles above Sabula drawbridge","at marker 540, 5 miles above the sub Bula drawbridge", 540];
    public static $m541 = ["at marker 541 by Palisades state park campground","at marker 541, by the Palisades state park campground entrance", 541];
    public static $m542 = ["at marker 542 7 miles above Sabula drawbridge","at marker 542, 7 miles above the sub Bula drawbridge", 542];
    public static $m543 = ["at marker 543 by Palisades Golf Course","at marker 543 by Palisades Golf Course", 543];
    public static $m544 = ["at marker 544, 1 mile below Ka-Ching Global Sourcing","at marker 544, 1 miles below Ka-Ching Global Sourcing", 544];
    public static $m545 = ["at marker 545 by Ka-Ching Global Sourcing","at marker 545 by Ka-Ching Global Sourcing", 545];
    public static $m546 = ["at marker 546 by TLC Rail Services","at marker 546 by TLC Rail Services", 546];
    public static $m547 = ["at marker 547 by Green Island, Iowa","at marker 547 by Green Island, Iowa", 547];
    public static $m548 = ["at marker 548 by the mouth of the Maquoketa River","at marker 548 by the mouth of the Maquoketa River", 548];
    public static $m549 = ["at marker 549, a half mile above the Maquoketa River","at marker 549 a half mile above the Maquoketa River", 549];
    public static $m550 = ["at marker 550, 6 miles below Bellevue","at marker 550, 6 miles below Bellevue", 550];
    public static $m551 = ["at marker 551, 5 miles below Bellevue","at marker 551, 5 miles below Bellevue", 551];
    public static $m552 = ["at marker 552, 4 miles below Bellevue","at marker 552, 4 miles below Bellevue", 552];
    public static $m553 = ["at marker 553, 3 miles below Bellevue","at marker 553, 3 miles below Bellevue", 553];
    public static $m554 = ["at marker 554, 2 miles below Bellevue","at marker 554, 2 miles below Bellevue", 554];
    public static $m555 = ["at marker 555, 1 mile below Bellevue","at marker 555, 1 mile below Bellevue", 555];
    public static $m556 = ["at marker 556 just below lock 12 in Bellevue","at marker 556 just below lock 12 in Bellevue", 556];
    public static $m557 = ["at marker 557 just above lock 12 in Bellevue","at marker 557 just above lock 12 in Bellevue", 557];
    public static $m558 = ["at marker 558, 1 mile above lock 12","at marker 558, 1 mile above lock 12", 558];
    public static $m559 = ["at marker 559, 2 miles above lock 12","at marker 559, 2 miles above lock 12", 559];
    public static $m560 =  ["at marker 560, 3 miles above lock 12","at marker 560, 3 miles above lock 12", 560];
    public static $m561 =  ["at marker 561, 4 miles above lock 12","at marker 561, 4 miles above lock 12", 561];
    public static $m562 =  ["at marker 562, 5 miles above lock 12","at marker 562, 5 miles above lock 12", 562];
    public static $m563 =  ["at marker 563, 6 miles above lock 12","at marker 563, 6 miles above lock 12", 563];
    public static $m564 =  ["at marker 564, 7 miles above lock 12","at marker 564, 7 miles above lock 12", 564];
    public static $m565 =  ["at marker 565, 8 miles above lock 12","at marker 565, 8 miles above lock 12", 565];
    public static $m566 =  ["at marker 566, 9 miles above lock 12","at marker 566, 9 miles above lock 12", 566];
    public static $m567 =  ["at marker 567, 10 miles above lock 12","at marker 567, 10 miles above lock 12", 567];
    public static $m568 =  ["at marker 568, 11 miles above lock 12","at marker 568, 11 miles above lock 12", 568];
    public static $m569 =  ["at marker 569, 12 miles above lock 12","at marker 569, 12 miles above lock 12", 569];

    public static $m570 = ["at marker 570, 13 miles above lock 12","at marker 570, 13 miles above lock 12", 570];
    public static $m571 = ["at marker 571, 14 miles above lock 12","at marker 571, 14 miles above lock 12", 571];
    public static $m572 = ["at marker 572, 15 miles above lock 12","at marker 572, 15 miles above lock 12", 572];
    public static $m573 = ["at marker 573, 16 miles above lock 12","at marker 573, 16 miles above lock 12", 573];
    public static $m574 = ["at marker 574, 17 miles above lock 12","at marker 574, 17 miles above lock 12", 574];
    public static $m575 = ["at marker 575, 18 miles above lock 12","at marker 575, 18 miles above lock 12", 575];
    public static $m576 = ["at marker 576, 19 miles above lock 12","at marker 576, 19 miles above lock 12", 576];
    public static $m577 = ["at marker 577, 20 miles above lock 12","at marker 577, 20 miles above lock 12", 577];
    public static $m578 = ["at marker 578, 21 miles above lock 12","at marker 578, 21 miles above lock 12", 578];
    
    public static $malpha = ["3 miles N of Lock 13", "3 miles north of Lock 13", 525];
    public static $mbravo = ["at Lock 13, Fulton", "at Lock 13 by Fulton, Illinois", 522];
    public static $mcharlie = ["at RR drawbridge, Clinton", "at Clinton's railroad drawbridge", 518];
    public static $mdelta = ["3 miles S of RR drawbridge","3 miles south of Clinton's railroad drawbridge", 515];
    public static $mecho = ["at the I-80 bridge, LeClaire", "at the interstate 80 bridge in LeClaire", 495];
    public static $mfoxtrot = ["at Lock 14, Princeton","at Lock 14 by Princeton, Iowa", 493];
    public static $mgolf = ["at Lock 15, Davenport", "at Lock 15 in Davenport", 482];
    public static $mhotel = ["at I-280 bridge, Davenport", "at the Interstate two-eighty bridge in Davenport", 478];
    public static $mlakepotter = ["at Sunset Marina, Rock Island", "at Lake Potter, the home of Sunset Marina in Rock Island", 479];
    public static $mcredit_island_slough = ["in Davenport Harbor", "at Davenport Harbor in Credit Island Slough",479];

}
