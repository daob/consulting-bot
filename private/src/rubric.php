<?php
/**
 * The rubric, as data. Items A and B and C come from Kirk (1991). Block D is
 * course-specific and deliberately has no answer key: good advice takes many
 * forms, so the assessor is asked whether the questions were dealt with, whether
 * anything said was wrong, and whether the plan the client leaves with is sane.
 */
declare(strict_types=1);

function cc_rubric(array $persona): array
{
    $items = [
        ['id'=>'A1','block'=>'Process','text'=>'Established some rapport before getting into the problem, and moved on once it had served its purpose.'],
        ['id'=>'A2','block'=>'Process','text'=>'Let the client do the talking early on, asking questions to clarify rather than diagnosing straight away.'],
        ['id'=>'A3','block'=>'Process','text'=>'Checked whether this is the real client and who else is involved in the project.'],
        ['id'=>'A4','block'=>'Process','text'=>'Understood what the study is actually about and what is at stake in it, not only its variables.'],
        ['id'=>'A5','block'=>'Process','text'=>'Restated the problem back to the client before proposing anything, and checked they had it right.'],
        ['id'=>'A6','block'=>'Process','text'=>'Turned the research question into a statistical question the client recognised as her own.'],
        ['id'=>'A7','block'=>'Process','text'=>'Established how the data were collected and who controls them.'],
        ['id'=>'A8','block'=>'Process','text'=>'Agreed explicitly who does what next, and by when.'],
        ['id'=>'A9','block'=>'Process','text'=>'Summed up at the end, and asked whether there was anything else they should know.'],

        ['id'=>'B1','block'=>'Relationship','text'=>'Noticed which role was being pushed onto them - technician, decision-maker, rubber stamp, collaborator, teacher - and negotiated it rather than accepting it by default.'],
        ['id'=>'B2','block'=>'Relationship','text'=>'Responded to the concern behind the client\'s behaviour rather than only to the behaviour.'],
        ['id'=>'B3','block'=>'Relationship','text'=>'Showed they understood the client\'s position without implying they agreed with it, and avoided attacking it.'],
        ['id'=>'B4','block'=>'Relationship','text'=>'Used open, non-directive moves - restating, acknowledging, leaving space - before directive ones.'],

        ['id'=>'C1','block'=>'Level and honesty','text'=>'Pitched the recommendation at the client\'s actual level: the simplest thing that answers her question, preferably something usual in her field.'],
        ['id'=>'C2','block'=>'Level and honesty','text'=>'Neither dazzled her with technique nor did the thinking for her; asked her to stretch where she showed willingness.'],
        ['id'=>'C3','block'=>'Level and honesty','text'=>'Was willing to say they did not know, or needed to look something up, rather than improvising.'],
        ['id'=>'C4','block'=>'Level and honesty','text'=>'Dealt with decisions that cannot now be undone without re-litigating them.'],
    ];

    // Block D: one item per thing the client came to ask, then the two
    // course-specific checks. There is no answer key: good advice takes many
    // forms, so we ask whether the questions were dealt with, whether anything
    // said was false, and whether the plan she leaves with hangs together.
    $n = 0;
    foreach ($persona['questions'] as $q) {
        $n++;
        $items[] = [
            'id'       => 'D' . $n,
            'block'    => 'Substance',
            'text'     => 'Her question about ' . $q['tag'] . ' was dealt with - answered, or explained '
                        . 'clearly why it cannot be answered as asked.',
            'guidance' => 'Partial if it was raised but left hanging. Not met if it never got a response '
                        . 'she could use. The question was: ' . $q['ask'],
        ];
    }

    $items[] = [
        'id'       => 'D' . (++$n),
        'block'    => 'Substance',
        'text'     => 'Nothing the consultant said was technically wrong.',
        'guidance' => 'Judge the statements actually made, not their completeness: a simple correct answer '
                    . 'is not a defect, and saying less than the whole truth is not an error. Flag only '
                    . 'assertions that are false - for example that full-information maximum likelihood '
                    . 'assumes data are missing not at random, that listwise deletion is the conservative '
                    . 'choice, or that a non-significant test establishes the absence of an effect. If '
                    . 'nothing was wrong, mark it met and say so in the comment.',
    ];

    $items[] = [
        'id'       => 'D' . (++$n),
        'block'    => 'Substance',
        'text'     => 'The plan she is left with makes sense.',
        'guidance' => 'Is what she now intends to do coherent, feasible for someone at her level, and '
                    . 'actually bearing on her question? There is more than one good answer; judge whether '
                    . 'this one hangs together, not whether it is the one you would have given.',
    ];

    return $items;
}

/** Report order is fixed; the order the assessor sees is not. See assess.php. */
function cc_rubric_blocks(): array
{
    return ['Process', 'Relationship', 'Level and honesty', 'Substance'];
}
