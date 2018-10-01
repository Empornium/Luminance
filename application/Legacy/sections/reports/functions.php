<?php

function report_guideline($Type, $Types)
{
    global $master;

    $Text = new \Luminance\Legacy\Text();

    // If we have a custom guideline for this specific type
    if ($Article = get_article("{$Type}report")) {
        return $Text->full_format($Article);
    }

    // If we have a custom default guideline article
    if ($Article = get_article("defaultreport")) {
        return $Text->full_format($Article);
    }

    // Fallback on default, hardcoded guidelines
    $guidelines = isset($Types[$Type]['guidelines']) ? $Types[$Type]['guidelines'] : [];
    return $master->render->render('snippets/report_guideline.html.twig', compact('guidelines'));
}
