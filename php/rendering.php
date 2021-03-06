<?php

require_once "engine.php";

function sign( $number ) {
    return ( $number > 0 ) ? 1 : ( ( $number < 0 ) ? -1 : 0 );
}

function GetPolygon($splines, $shiftx, $shifty) {
    // jump function to test both variants: orthogonal and middle angle vector method
    return GetPolygonMiddleAngle($splines, $shiftx, $shifty);
    //return GetPolygonOrthogonal($splines);
}

function GetPolygonMiddleAngle($splines, $shiftx, $shifty) {
    global $vector_value_precision;
    $color = $_SESSION['rendering_polygon_color'];
    $outer_line_thickness = 0.001;
     
    //var_dump($splines); echo "<br>";
    
    // initialize path variable
    $path4 = ""; // final spline path
    
    $i = 0;
    $spline_length = count($splines);
    // outer loop: repeat until $i==$length (= all shadows - if there are several - have been rendered)
    while ($i<$spline_length) {  
        // determine start and end of shadow
        $start = false;
        $end = false;
        // scan up to thickness > 1.0
        while (($i<$spline_length) && ($splines[$i+offs_th] == 1.0)) $i+=tuplet_length;
        if ($i<$spline_length) $start=$i;
        // if start found => scan up to end of shadow
        if ($start !== false) { // darned php ... 'auf' ended up in an endless loop sind start was found at position 0 ... which, by implicit type cast, is false ... *grrrrr*
            $left_spline = null;
            $right_spline = null;
            $final_spline = null;
            // initialize next path
            $path4 .= "\n<path d='"; // final spline path
            $thickest_thickness = 0;
            $thickest_tuplet = 0;
            while (($i<$spline_length) && ($splines[$i+offs_th] > 1.0)) {
                if ($splines[$i+offs_th] > $thickest_thickness) {
                    $thickest_thickness = $splines[$i+offs_th];
                    $thickest_tuplet = $i;
                }
                $i+=tuplet_length;
            }
            $end = $i;
      
            // due to early exit points there might be no tuplet with th == 1.0 towards the end
            // in that case, $end points to an inexisting tuplet at the very end of the array
            // correct this by setting $end to preceeding tuplet
            // NOTE: final tuplet (= last of array) in early exit points will be considered as ROUND
            // (so it ends "smoothly" whereas in SE1 it ends "abruptly" like a sharp token)
            // There's a similar problem with early exit points followed by a non connecting knot.
            // This happens for example with words followed by a coma ("Gang,", the coma is included
            // in the same SVG as the word).
            // In that case, $end points to the following knot (bottom of coma) and the polygon
            // goes to this point in a triangular form. To avoid that, check if early exit point
            // is followed by dr-field == 5 (= not connecting) and set end -= tuplet_length if so.
            if ($end == $spline_length) $end=$spline_length-tuplet_length;
            elseif ($splines[$end+offs_dr] == 5) $end -= tuplet_length;
       
            //echo "<br><br>spline_length: $spline_length, i: $i, start: $start, end: $end thickest: $thickest_thickness at $thickest_tuplet<br>";
            
            // determine if start and end points are sharp
            $start_sharp = ($splines[$start+offs_x1] == $splines[$start+offs_t1]) ? true : false;
            $end_sharp = ($splines[$end+offs_x1] == $splines[$end+offs_t1]) ? true : false;
            // fix bug (?) in CalculateWord() ??? (more a workaround than a fix, but at this point I'm not sure if it really comes from CalculateWord() and what other implications it has)
            // offs_t1 of last tuplet is 0 if knot is sharp
            $end_sharp = (($end+tuplet_length == $spline_length) && ($splines[$end+offs_t1] == 0)) ? true : $end_sharp;
    
            //echo "start_sharp: #$start_sharp# end_sharp: #$end_sharp#<br>";
            if (($start_sharp) && ($_SESSION['rendering_lineoverpass_yesno']) && ($_SESSION['rendering_sharp_modelling'] === "horizontal")) {
                // tokens that start and end with horizontal line (horizontal modelling) look different from smoothly rendered tokens
                // because they do not overpass the line (like smooth tokens), but follow exactly the horizontal line.
                // This can be corrected with the option 'rendering_lineoverpass_yesno'. In this case, patch the original splines
                // by placing knot a little bit 'higher' (= above the horizontal line). Distance from horizontal line to corrected
                // knot should be thickness / 2.
                //echo "calculate lineoverpass start<br>";
                // get start knot
                $snx = $splines[$start+offs_x1];
                $sny = $splines[$start+offs_y1];
                // vector to following knot
                $snvx = $snx - $splines[$start+tuplet_length+offs_x1];
                $snvy = $sny - $splines[$start+tuplet_length+offs_y1];
                // normalize vector
                $snvd = sqrt($snvx*$snvx + $snvy*$snvy);
                $snvx /= $snvd;
                $snvy /= $snvd;
                // calculate new knot and patch splines
                //echo "original knot: " . $splines[$start+offs_x1] . ", " . $splines[$start+offs_y1] . "<br>";
                $sth = $splines[$start+offs_th]; // thickness start knot
                $factor = (AdjustThickness($sth) / 2 -  $outer_line_thickness) * $_SESSION['rendering_lineoverpass_start_factor'];
                $splines[$start+offs_x1] = $snx + $factor * $snvx;
                $splines[$start+offs_y1] = $sny + $factor * $snvy;
                //echo "factor: $factor<br>";
                //echo "new knot: " . $splines[$start+offs_x1] . ", " . $splines[$start+offs_y1] . "<br>";
                // patch also control points
                // start knot
                $splines[$start+offs_qx1] = $splines[$start+offs_x1]; // tension = 0 = sharp = same value
                $splines[$start+offs_qy1] = $splines[$start+offs_y1];
                // preceeding
                if ($start>0) {
                    $splines[$start-tuplet_length+offs_qx2] = $splines[$start+offs_x1];
                    $splines[$start-tuplet_length+offs_qy2] = $splines[$start+offs_y1];
                }
            }
            // same for end knot
            if (($end_sharp) && ($_SESSION['rendering_lineoverpass_yesno']) && ($_SESSION['rendering_sharp_modelling'] === "horizontal")) {
                //echo "calculate lineoverpass end<br>";
                // get end knot
                $snx = $splines[$end+offs_x1];
                $sny = $splines[$end+offs_y1];
                // vector to preceeding knot
                $snvx = $snx - $splines[$end-tuplet_length+offs_x1];
                $snvy = $sny - $splines[$end-tuplet_length+offs_y1];
                // normalize vector
                $snvd = sqrt($snvx*$snvx + $snvy*$snvy);
                $snvx /= $snvd;
                $snvy /= $snvd;
                // calculate new knot and patch splines
                //echo "original knot: " . $splines[$end+offs_x1] . ", " . $splines[$end+offs_y1] . "<br>";
                $sth = $splines[$end-tuplet_length+offs_th]; // thickness end knot = preceeding thickness (se1) 
                $factor = (AdjustThickness($sth) / 2 -  $outer_line_thickness) * $_SESSION['rendering_lineoverpass_end_factor'];
                $splines[$end+offs_x1] = $snx + $factor * $snvx;
                $splines[$end+offs_y1] = $sny + $factor * $snvy;
                //echo "new knot: " . $splines[$end+offs_x1] . ", " . $splines[$end+offs_y1] . "<br>";
                // patch also control points
                // start knot
                $splines[$end+offs_qx1] = $splines[$end+offs_x1]; // tension = 0 = sharp = same value
                $splines[$end+offs_qy1] = $splines[$end+offs_y1];
                // preceeding
                if ($end>0) {
                    $splines[$end-tuplet_length+offs_qx2] = $splines[$end+offs_x1];
                    $splines[$end-tuplet_length+offs_qy2] = $splines[$end+offs_y1];
                }
            }
            
            // if start (and end) found => calculate polygon for shadow
            for ($r=$start; $r<=$end; $r+=tuplet_length) {
                //echo "tuplet: r = $r<br>";
                // initialize variables
                $px = null;
                $py = null;
                $fx = null;
                $fy = null;
                $use_this_thickness = null;
                $x = $splines[$r+offs_x1]; // + 25;
                $y = $splines[$r+offs_y1];
                $tt1 = $splines[$r+offs_t1];
                $tt2 = $splines[$r+offs_t2];
                //echo "x = $x; y = $y; t1 = $tt1; t2 = $tt2<br>";
            
                // calculate outer knot
                // needs: perpendicular vector relative to straight line that goes through preceeding and following knot
                // get coordinates of preceeding and following know
                if ($r >= tuplet_length) {
                    // preceeding knot exists
                    if (($x != $tt1) && ($tt1 != 0)) { // x != tt1 means: original t1 == 0.5 (we are AFTER CalculateWord())
                        // condition $tt1 != 0: necessary because last tt1 in spline (= very end of the word/spline) is 0 if line is sharp
                        // this is probably a bug in CalculateWord (so the additional condition is just a workaround ...)
                        // entry tension is > 0 (round)
                        //echo "tuplet $r has tension != 0 (round)<br>";
                        $px = $splines[$r-tuplet_length+offs_x1]; // preceeding x (assume it exists)
                        $py = $splines[$r-tuplet_length+offs_y1]; //            y
                    } else {
                        //echo "tuplet $r has tension 0.0 (sharp)<br>";
                        if ($r == $start) {
                            //echo "start knot: set px/py/fx/fy<br>";
                            $px = $splines[$r+offs_x1];
                            $py = $splines[$r+offs_y1];
                            //echo "set fx/fy to x = $x, y = $y<br>";
                            $fx = $splines[$r+tuplet_length+offs_x1];;
                            $fy = $splines[$r+tuplet_length+offs_y1];;
                            $use_this_thickness = $splines[$r+offs_th];
                            //echo "px = $px, py = $py, fx = $fx, fy = $fy<br>";
                        } elseif ($r == $end) {
                            //echo "end knot: set px/py/fx/fy<br>";
                            $px = $splines[$r-tuplet_length+offs_x1];
                            $py = $splines[$r-tuplet_length+offs_y1];
                            //echo "set fx/fy to x = $x, y = $y<br>";
                            $fx = $splines[$r+offs_x1];;
                            $fy = $splines[$r+offs_y1];;
                            $use_this_thickness = $splines[$r-tuplet_length+offs_th]; // use preceeding thickness (end == "decreasing" part)
                            //echo "px = $px, py = $py, fx = $fx, fy = $fy<br>";
                        } 
                    }
                } else {
                    // no preceeding knot => set it to central knot x, y
                    //echo "no preceeding knot<br>";
                    if ($r == $start) {
                        //echo "start knot: set px/py/fx/fy<br>";
                        $px = $splines[$r+offs_x1];
                        $py = $splines[$r+offs_y1];
                        $fx = $splines[$r+tuplet_length+offs_x1];;
                        $fy = $splines[$r+tuplet_length+offs_y1];;
                        $use_this_thickness = $splines[$r+offs_th];
                        //echo "px = $px, py = $py, fx = $fx, fy = $fy<br>";
                    } elseif ($r == $end) {
                        //echo "end knot: set px/py/fx/fy<br>";
                        $px = $splines[$r-tuplet_length+offs_x1];
                        $py = $splines[$r-tuplet_length+offs_y1];
                        $fx = $splines[$r+offs_x1];;
                        $fy = $splines[$r+offs_y1];;
                        $use_this_thickness = $splines[$r-tuplet_length+offs_th]; // use preceeding thickness (end == "decreasing" part)
                        //echo "px = $px, py = $py, fx = $fx, fy = $fy<br>";
                    } else { 
                        $px = $x;
                        $py = $y;
                    }
                }
                if ($fx === null) {
                    //echo "fx hasn't been set => set it<br>";
                    if ($r + tuplet_length < $spline_length) {
                        // following knot exists
                        $fx = $splines[$r+tuplet_length+offs_x1]; // following x 
                        $fy = $splines[$r+tuplet_length+offs_y1]; //           y
                    } else {
                        // no following knot => set it to central knot x, y
                        $fx = $x;
                        $fy = $y;
                    }
                }
          
                //echo "preceeding: $px, $py - central: $x, $y - following: $fx, $fy<br>";
                // recalculate vectors: must be same length to get middle angle
                // offer middleangle as an option via session variable
                if (($_SESSION['rendering_vector_type'] === "middleangle") && ((($px != $x) && ($py != $y)) && (($x != $fx) && ($y != $fy)))) {
                    // only do recaculation if there are really three differents points that are found
                    //echo "recalculate middleangle<br>";
                    $v1x = $x - $px;
                    $v1y = $y - $py;
                    $v1d = sqrt($v1x*$v1x + $v1y*$v1y);
                    $v2x = $x - $fx;
                    $v2y = $y - $fy;
                    $v2d = sqrt($v2x*$v2x + $v2y*$v2y);
                    $oldpx = $px;
                    $oldpy = $py;
                    $px = $x - $v1x / $v1d * $v2d;    // make v1 the same length as v2
                    $py = $y - $v1y / $v1d * $v2d;
                    //echo "corrected: old: $oldpx, $oldpy; new: $px, $py<br>";
                    //echo "v1: $v1x, $v1y; v1d: $v1d; v2: $v2x, $v2y; v2d: $v2d<br>";
                    //echo "preceeding: $px, $py - central: $x, $y - following: $fx, $fy<br>";
                }
        
                // calculate vector for straight line (g)
                $vgx = $fx-$px;
                $vgy = $fy-$py;
                // normal vector (left) = rotate -90 degrees => negate x, then flip x and y and divide by length (d)
                $d = sqrt($vgx*$vgx + $vgy*$vgy);
                $nvx = - $vgy / $d;
                $nvy = $vgx / $d;
                
                // correct normal vector for horizontal modelling
                if (((($r == $start) && ($start_sharp)) || (($r == $end) && ($end_sharp))) && ($_SESSION['rendering_sharp_modelling'] === "horizontal")) {
                    //echo "correct vector for horizontal modelling<br>";
                    //echo "r vs start vs end: $r / $start / $end<br>";
                    //echo "start_sharp vs end_sharp: >$start_sharp< >$end_sharp<<br>";
                    $angle = $_SESSION['token_inclination'];
                    $rad = deg2rad($angle);
                    $f = 1/sin($rad); // place that at beginning of function for performance reasons
                    $oldnvx = $nvx;
                    $oldnvy = $nvy;
                    //echo "oldnv: $oldnvx, $oldnvy<br>";
                    $sign = sign($nvx);
                    $nvx = $sign * $f;
                    $nvy = 0;
                    //echo "newnv: $nvx, $nvy<br>";
                    //echo "sign: $sign angle: $angle; rad: $rad; f: $f nvx: $nvx; nvy: $nvy<br>";
                }// elseif ($_SESSION['rendering_sharp_modelling'] === "tangent") {
            
            
                // normal vector right = same as left but with negated nvx, nvy (include it directly in the calculation)
                //echo "vectors: line = $vgx, $vgy length = $d normal vector = $nvx, $nvy<br>";
                // new knot = old knot + normal vector * thinkness / 2 (half of th = length of vector)
                // include polygon lines in total area (= subtract outer line thickness)
                // variable thickness: idea is to user thickness 1.0 at start / end points if there are only 
                // three knots that form polygon shape in order to get a smoother shadow
                // for tokens that have more than three knots on the other hands it is better to start
                // and end with a sharp point (thickness 0)
                $short_shape = ($end-$start) == 16 ? true : false; // 16 = 2*tuplet_length => polygon shape will be modelled with 3 knots
                if ($short_shape) $variable_thickness = 1.0; // case "rück"
                else $variable_thickness = 0; // case "Haspel"
                if ($use_this_thickness === null) {
                    if (($r > $thickest_tuplet) && ($r < $end)) {
                        //echo "tuplet: $r is between $thickest_tuplet and $end<br>";
                        // $r between $thickest_tuplet and $end means: we are in "decreasing" part of shadow
                        // i.e. token gets thinner => at this point: use last thickness for calculation of vectors
                        // since in middle line modelling the thickness continues until the end of the corresponding part!
                        //$use_this_thickness = $thickest_thickness; // use thickest_thickness for beginning and end of thickest part
                        $use_this_thickness = $splines[$r-tuplet_length+offs_th];
                    } elseif (($r == $start) && (!$start_sharp)) $use_this_thickness = $variable_thickness; // maybe modify thickness depending on number of shadowed knots
                    elseif (($r == $end) && (!$end_sharp)) $use_this_thickness = $variable_thickness;
                }
                $th = ($use_this_thickness === null) ? $splines[$r+offs_th] : $use_this_thickness;
                // adjust thickness with scaling factors
                //$th = $th * $_SESSION['token_size'] / $correction_shadow_factor * $_SESSION['token_shadow'];
                $th = $_SESSION['token_thickness'] * AdjustThickness($th);
                
                //echo "tuplet: $r thickness (se2): $th<br>";
                $olx = $x + $nvx * ($th / 2 - $outer_line_thickness); // ol = outer left
                $oly = $y + $nvy * ($th / 2 - $outer_line_thickness);
                $orx = $x - $nvx * ($th / 2 - $outer_line_thickness); // or = outer right (negated x, y of normal vector)
                $ory = $y - $nvy * ($th / 2 - $outer_line_thickness);
                //echo "new knot (th = $th):<br>left:<br>olx = $x + $nvx * ($th / 2 - $outer_line_thickness) = $olx<br>oly = $y + $nvy * $th = $oly<br>";
                //echo "right:<br>orx = $x - $nvx * $th = $orx<br>ory = $y - $nvy * $th = $ory<br>";
            
                // the problem now is that, since middle line is still drawn with thickness 1.0
                // and "orthogonal" modelling, there are small triangles "sticking out" of modelled
                // polygon ... The only way to avoid that is by modifying the calling spline array,
                // so that the entry and exit knots (including orthogonal middle line modelling)
                // lay inside the polygon. Therefore, treat calling spline as reference and modify
                // entry and exit knot accordingly.
                // NOTE: call by reference, i.e. GetPolygon(&$splines), doesn't seem to work properly.
                // Therefore make GetPolygon() a function that returns a list of 2 values: svg-shape
                // and modified splines.
                // modify entry/exit knots: place them near the upper left/lower right corner of polygon
                // IMPORTANT: since this correction modifies original splines, if middle line modelling
                // is selected at the same time as polygon rendering, there will be 2 thick parts with
                // different angles! Only the polygon shape is shown with the CORRECT angle. The original
                // middle line shows a wrong angle since the entry / exit coordinates have been modified!
                if ($_SESSION['rendering_sharp_modelling'] === "horizontal") {
                    //echo "correct entry/exit knots for horizontal modelling<br>";
                    //echo "r: $r start: $start end: $end<br>";
                    if (($r == $start) && ($start_sharp)) {
                        // user upper left for entry knot
                        // use vgx/y to place it "near" the corner
                        // (it it's directly on the corner, triangles will stick out again)
                        $line_th = AdjustThickness(1.0);
                        $nlx = $x + $nvx * ($th / 2 - $outer_line_thickness - $line_th); // ol = outer left
                        $nly = $y + $nvy * ($th / 2 - $outer_line_thickness - $line_th);
                        $nrx = $x - $nvx * ($th / 2 - $outer_line_thickness - $line_th); // or = outer right (negated x, y of normal vector)
                        $nry = $y - $nvy * ($th / 2 - $outer_line_thickness - $line_th);
           
                        $vgxd = sqrt($vgx*$vgx + $vgy*$vgy);
                        $nvgx = $vgx / $vgxd;
                        $nvgy = $vgy / $vgxd;
                        $fn1x = $nlx+ $nvgx * ($line_th / 2 - $outer_line_thickness); // final new x1
                        $fn1y = $nly + $nvgy * ($line_th / 2 - $outer_line_thickness);
                        //echo "old: $x, $y; olxy: $olx, $oly fn1xy: $fn1x, $fn1y<br>";
                  
                        $splines[$r+offs_x1] = $fn1x; //$olx; 
                        $splines[$r+offs_y1] = $fn1y; //$oly; 
                        $splines[$r+offs_qx1] = $fn1x; // $olx;
                        $splines[$r+offs_qy1] = $fn1y; // $oly;
                        // additionally modify preceeding control points if knot exists
                        if ($r>0) {
                            $splines[$r-tuplet_length+offs_qx2] = $fn1x; // $olx; //$fn1x;
                            $splines[$r-tuplet_length+offs_qy2] = $fn1y; //$oly; //$fn1y;
                        }
                    } elseif (($r == $end) && ($end_sharp)) {
                        // user lower right for entry knot
                        // use vgx/y to place it "near" the corner
                        // (it it's directly on the corner, triangles will stick out again)
                        $line_th = AdjustThickness(1.0);
                        $nlx = $x + $nvx * ($th / 2 - $outer_line_thickness - $line_th); // ol = outer left
                        $nly = $y + $nvy * ($th / 2 - $outer_line_thickness - $line_th);
                        $nrx = $x - $nvx * ($th / 2 - $outer_line_thickness - $line_th); // or = outer right (negated x, y of normal vector)
                        $nry = $y - $nvy * ($th / 2 - $outer_line_thickness - $line_th);
           
                        $vgxd = sqrt($vgx*$vgx + $vgy*$vgy);
                        $nvgx = $vgx / $vgxd;
                        $nvgy = $vgy / $vgxd;
                        $fn1x = $nrx - $nvgx * ($line_th / 2 - $outer_line_thickness); // final new x1
                        $fn1y = $nry - $nvgy * ($line_th / 2 - $outer_line_thickness);
                        //echo "old: $x, $y; orxy: $orx, $ory fn1xy: $fn1x, $fn1y<br>";
                
                        $splines[$r+offs_x1] = $fn1x; // $orx; 
                        $splines[$r+offs_y1] = $fn1y; // $ory;
                        $splines[$r+offs_qx1] = $fn1x; // $orx;
                        $splines[$r+offs_qy1] = $fn1y; //$ory;
                        // additionally modify control points of preceeding knot if it exists
                        if ($r>0) {
                            $splines[$r-tuplet_length+offs_qx2] = $fn1x; //$orx; //$fn1x; // qx1 inside same tuplet
                            $splines[$r-tuplet_length+offs_qy2] = $fn1y; //$ory; //$fn1y;
                        }
                    }
                }

                // write knots to arrays
                // tensions:
                // 0.5 if point is round
                // 0.0 if point is sharp (start and end)
                // left
                $left_spline[] = $olx;
                $left_spline[] = $oly;
                $left_spline[] = ((($r == $start) && ($start_sharp)) || (($r == $end) && ($end_sharp))) ? 0.0 : 0.5; // t1
                $left_spline[] = 0;
                $left_spline[] = 1.0; // th
                $left_spline[] = 0;
                $left_spline[] = 0;
                $left_spline[] = ((($r == $start) && ($start_sharp)) || (($r == $end) && ($end_sharp))) ? 0.0 : 0.5; // t2
                // right
                $right_spline[] = $orx;
                $right_spline[] = $ory;
                $right_spline[] = ((($r == $start) && ($start_sharp)) || (($r == $end) && ($end_sharp))) ? 0.0 : 0.5; // t1
                $right_spline[] = 0;
                $right_spline[] = 1.0; // th
                $right_spline[] = 0;
                $right_spline[] = 0;
                $right_spline[] = ((($r == $start) && ($start_sharp)) || (($r == $end) && ($end_sharp))) ? 0.0 : 0.5; // t2
            }
            // close path
        
            // test bezier calculation with right spline
            // calculate control points: this is the same calculation as for word (we can therefore "abuse" the function CalculateWord)
            //echo "calculate final spline<br>";
            // compose final spline combinig right and left splines
            // first copy over right spline
            $final_spline = $right_spline;
            $patch_first_right = count($final_spline);
            $patch_last_left = $patch_first_right - tuplet_length;
            // connect right and left spline by a straight line
            // to do so, set entry/exit tensions 0 in last and first knots
            // (this makes it easier to calculate final polygon shape since function CalculateWord can be used)
            // this can only be done once all data has been copied, so for the moment just mark position of tuplet
            $connecting_tuplet = count($final_spline)-tuplet_length;
            // add left spine in inverted order
            $length = count($left_spline);
            //echo "length left spline: $length<br>";
            //var_dump($left_spline);
            for ($ii=$length-tuplet_length; $ii>=0; $ii-=tuplet_length) {
                //echo "add tuplet $i<br>";
                $final_spline[] = $left_spline[$ii+offs_x1];
                $final_spline[] = $left_spline[$ii+offs_y1];
                $final_spline[] = $left_spline[$ii+offs_t1];
                $final_spline[] = $left_spline[$ii+offs_d1];
                $final_spline[] = $left_spline[$ii+offs_th];
                $final_spline[] = $left_spline[$ii+offs_dr];
                $final_spline[] = $left_spline[$ii+offs_d2];
                $final_spline[] = $left_spline[$ii+offs_t2];
            }
            // now set tensions for connecting tuplet
        
            //$final_spline[$connecting_tuplet-1] = 0.0; // (preceeding) entry tension 1st knot
            $final_spline[$connecting_tuplet+offs_t1] = 0.0; // exit tension 1st knot
            $final_spline[$connecting_tuplet+offs_t2] = 0.0; // entry tension 2nd knot
            //$final_spline[$connecting_tuplet+tuplet_length+offs_t1] = 0.0; // (following) exit tension 2nd knot
        
            // calculate complete polygon spline
            $final_spline = CalculateWord($final_spline);
            // now patch result with start/end control points 
            // start with first knot in final spline on the left side
            if (($short_shape) && ($start>0) && (!$start_sharp)) {
                $tpx0 = $splines[$start-tuplet_length+offs_x1]; // temp point x0; get that knot from original spline to calculate control points
                $tpy0 = $splines[$start-tuplet_length+offs_y1];
                $tpx1 = $final_spline[0+offs_x1]; 
                $tpy1 = $final_spline[0+offs_y1];
                $tpx2 = $final_spline[0+tuplet_length+offs_x1]; 
                $tpy2 = $final_spline[0+tuplet_length+offs_y1];
                list( $c1x, $c1y, $c2x, $c2y) = GetControlPoints( $tpx0, $tpy0, $tpx1, $tpy1, $tpx2, $tpy2, 0.5, 0.5); 
                //echo "p0: $tpx0, $tpy0; p1: $tpx1, $tpy1; p2: $tpx2, $tpy2<br>";
                //echo "c1: $c1x, $c1y; c2: $c2x, $c2y<br>";
                //echo "splines c2: " . $splines[$start+offs_qx1] . ", " . $splines[$start+offs_qy1] . "<br>";
                $final_spline[0+offs_qx1] = $c2x; //$splines[$start+offs_qx1];
                $final_spline[0+offs_qy1] = $c2y; //$splines[$start+offs_qy1];
            }
            // do the same with last left
            if (($short_shape) && ($end+tuplet_length <= $spline_length) && (!$end_sharp)) {
                //var_dump($splines);
                // echo "<br>";
                //var_dump($final_spline);
                //echo "<br>";
            
                //echo "end: $end; patch_last_left: $patch_last_left<br>";
                $tpx0 = $final_spline[$patch_last_left-tuplet_length+offs_x1]; // temp point x0; get that knot from original spline to calculate control points
                $tpy0 = $final_spline[$patch_last_left-tuplet_length+offs_y1];
                $tpx1 = $final_spline[$patch_last_left+offs_x1]; 
                $tpy1 = $final_spline[$patch_last_left+offs_y1];
                $tpx2 = $splines[$end+tuplet_length+offs_x1]; 
                $tpy2 = $splines[$end+tuplet_length+offs_y1];
                list( $c1x, $c1y, $c2x, $c2y) = GetControlPoints( $tpx0, $tpy0, $tpx1, $tpy1, $tpx2, $tpy2, 0.5, 0.5); 
                //echo "p0: $tpx0, $tpy0; p1: $tpx1, $tpy1; p2: $tpx2, $tpy2<br>";
                //echo "c1: $c1x, $c1y; c2: $c2x, $c2y<br>";
                //echo "splines c2: " . $splines[$start+offs_qx1] . ", " . $splines[$start+offs_qy1] . "<br>";
                $final_spline[$patch_last_left-tuplet_length+offs_qx2] = $c1x; 
                $final_spline[$patch_last_left-tuplet_length+offs_qy2] = $c1y;             
            }
            // do the same with first right
            // example spanish "para" (= A[AR]); first right can't be done because spline ends here
            // following if block must be tested with another word!!!
            if (($short_shape) && ($end+tuplet_length < $spline_length) && (!$end_sharp)) {
                //echo "<br>patch first right: end = $end<br><br>";
                //var_dump($splines);
                //echo "<br><br>";
                //var_dump($final_spline);
                //echo "<br>";
            
                //echo "end: $end; patch_last_left: $patch_last_left<br>";
                $tpx0 = $splines[$end+tuplet_length+offs_x1]; // temp point x0; get that knot from original spline to calculate control points
                $tpy0 = $splines[$end+tuplet_length+offs_y1];
                $tpx1 = $final_spline[$patch_first_right+offs_x1]; 
                $tpy1 = $final_spline[$patch_first_right+offs_y1];
                $tpx2 = $final_spline[$patch_first_right+tuplet_length+offs_x1]; 
                $tpy2 = $final_spline[$patch_first_right+tuplet_length+offs_y1];
                list( $c1x, $c1y, $c2x, $c2y) = GetControlPoints( $tpx0, $tpy0, $tpx1, $tpy1, $tpx2, $tpy2, 0.5, 0.5); 
                //echo "p0: $tpx0, $tpy0; p1: $tpx1, $tpy1; p2: $tpx2, $tpy2<br>";
                //echo "c1: $c1x, $c1y; c2: $c2x, $c2y<br>";
                //echo "splines c2: " . $splines[$start+offs_qx1] . ", " . $splines[$start+offs_qy1] . "<br>";
                $final_spline[$patch_first_right+offs_qx1] = $c2x; 
                $final_spline[$patch_first_right+offs_qy1] = $c2y;             
            } elseif (($short_shape) && ($splines[$end+offs_x1] === $splines[0+offs_x1]) && ($splines[$end+offs_y1] === $splines[0+offs_y1])) {
                // case "para" (spanish): token is round which means: last point of shape is identical to first point
                // use this to get one more point to calculate bezier control point (getting such an additional knot is crucial
                // since the polygon shape only relies on 3 knots, out of which 2 have thickness 0 ...)
                //echo "circular token ... <br>";
                $tpx0 = $splines[8+offs_x1]; // use second tuplet from start in case of circular token
                $tpy0 = $splines[8+offs_y1];
                $tpx1 = $final_spline[$patch_first_right+offs_x1]; 
                $tpy1 = $final_spline[$patch_first_right+offs_y1];
                $tpx2 = $final_spline[$patch_first_right+tuplet_length+offs_x1]; 
                $tpy2 = $final_spline[$patch_first_right+tuplet_length+offs_y1];
                list( $c1x, $c1y, $c2x, $c2y) = GetControlPoints( $tpx0, $tpy0, $tpx1, $tpy1, $tpx2, $tpy2, 0.5, 0.5); 
                //echo "p0: $tpx0, $tpy0; p1: $tpx1, $tpy1; p2: $tpx2, $tpy2<br>";
                //echo "c1: $c1x, $c1y; c2: $c2x, $c2y<br>";
                //echo "splines c2: " . $splines[$start+offs_qx1] . ", " . $splines[$start+offs_qy1] . "<br>";
                $final_spline[$patch_first_right+offs_qx1] = $c2x; 
                $final_spline[$patch_first_right+offs_qy1] = $c2y;             
            }

            // do last correction (patch) with last element of final spline
            $last_element = count($final_spline)-tuplet_length;
            if (($short_shape) && ($start>0) && (!$start_sharp)) {
                // echo "<br>patch last element in final spline: last_element = $last_element; start = $start<br><br>";
                //var_dump($splines);
                //echo "<br><br>";
                //var_dump($final_spline);
                //echo "<br>";
            
                //echo "end: $end; patch_last_left: $patch_last_left<br>";
                $tpx0 = $final_spline[$last_element-tuplet_length+offs_x1]; // temp point x0; get that knot from original spline to calculate control points
                $tpy0 = $final_spline[$last_element-tuplet_length+offs_y1];
                $tpx1 = $final_spline[$last_element+offs_x1]; 
                $tpy1 = $final_spline[$last_element+offs_y1];
                $tpx2 = $splines[$start-tuplet_length+offs_x1]; 
                $tpy2 = $splines[$start-tuplet_length+offs_y1];
                list( $c1x, $c1y, $c2x, $c2y) = GetControlPoints( $tpx0, $tpy0, $tpx1, $tpy1, $tpx2, $tpy2, 0.5, 0.5); 
                //echo "p0: $tpx0, $tpy0; p1: $tpx1, $tpy1; p2: $tpx2, $tpy2<br>";
                //echo "c1: $c1x, $c1y; c2: $c2x, $c2y<br>";
                //echo "splines c2: " . $splines[$start+offs_qx1] . ", " . $splines[$start+offs_qy1] . "<br>";
                $final_spline[$last_element-tuplet_length+offs_qx2] = $c1x; 
                $final_spline[$last_element-tuplet_length+offs_qy2] = $c1y;             
            
                // calculate also preceeding knot (bug in CalculateWord() - not calculated ...)
                $tpx2 = $tpx1;
                $tpy2 = $tpy1;
                $tpx1 = $tpx0;
                $tpy1 = $tpy0;
                $tpx0 = $final_spline[$last_element-2*tuplet_length+offs_x1];
                $tpy0 = $final_spline[$last_element-2*tuplet_length+offs_y1];
                list( $c1x, $c1y, $c2x, $c2y) = GetControlPoints( $tpx0, $tpy0, $tpx1, $tpy1, $tpx2, $tpy2, 0.5, 0.5); 
                //echo "p0: $tpx0, $tpy0; p1: $tpx1, $tpy1; p2: $tpx2, $tpy2<br>";
                //echo "c1: $c1x, $c1y; c2: $c2x, $c2y<br>";
                //echo "splines c2: " . $splines[$start+offs_qx1] . ", " . $splines[$start+offs_qy1] . "<br>";
                $final_spline[$last_element+offs_qx1] = $c2x; 
                $final_spline[$last_element+offs_qy1] = $c2y;             
            
                //echo "<br><br>";
                //var_dump($final_spline);
                //echo "<br>";
            }
        
            //var_dump($final_spline);
            $length = count($final_spline);
            $x1 = round($final_spline[offs_x1] + $shiftx, $vector_value_precision, PHP_ROUND_HALF_UP);
            $y1 = round($final_spline[offs_y1] + $shifty, $vector_value_precision, PHP_ROUND_HALF_UP);
            $path4 .= "M $x1 $y1 ";
            //echo "start: M $x1 $y1<br>";
        
            for ($ii=0; $ii<$length-tuplet_length; $ii+=tuplet_length) {
                // round($value, $vector_value_precision, PHP_ROUND_HALF_UP);
                $qx1 = round($final_spline[$ii+offs_qx1] + $shiftx, $vector_value_precision, PHP_ROUND_HALF_UP);
                $qy1 = round($final_spline[$ii+offs_qy1] + $shifty, $vector_value_precision, PHP_ROUND_HALF_UP);
                $qx2 = round($final_spline[$ii+offs_qx2] + $shiftx, $vector_value_precision, PHP_ROUND_HALF_UP);
                $qy2 = round($final_spline[$ii+offs_qy2] + $shifty, $vector_value_precision, PHP_ROUND_HALF_UP);
                $x2 = round($final_spline[$ii+tuplet_length+offs_x1] + $shiftx, $vector_value_precision, PHP_ROUND_HALF_UP);
                $y2 = round($final_spline[$ii+tuplet_length+offs_y1] + $shifty, $vector_value_precision, PHP_ROUND_HALF_UP);
                $path4 .= "C $qx1 $qy1 $qx2 $qy2 $x2 $y2 ";
                //echo "bezier $ii: C $qx1 $qy1 $qx2 $qy2 $x2 $y2<br>";
            } 
            // finish path4 
            //fill='none'
            $opacity = $_SESSION['rendering_polygon_opacity'];
            $path4 .= "Z' stroke='$color' stroke-width='$outer_line_thickness' style='fill:$color' fill-opacity='$opacity' shape-rendering='geometricPrecision' />"; // final bezier path        
        }
        //echo "i at the end of while loop: $i<br>";
        //echo "path4: " . htmlspecialchars($path4) . "<br>";
    }
    //var_dump($splines);
    return array($path4, $splines);
}

?>