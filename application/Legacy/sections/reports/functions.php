<?php

function report_guideline($Type, $Types)
{
    global $master;

    $bbCode = new \Luminance\Legacy\Text;

    // If we have a custom guideline for this specific type
    if ($Article = get_article("{$Type}report")) {
        return $bbCode->full_format($Article);
    }

    // If we have a custom default guideline article
    if ($Article = get_article("defaultreport")) {
        return $bbCode->full_format($Article);
    }

    // Fallback on default, hardcoded guidelines
    $guidelines = isset($Types[$Type]['guidelines']) ? $Types[$Type]['guidelines'] : [];
    return $master->render->template('snippets/report_guideline.html.twig', compact('guidelines'));
}
