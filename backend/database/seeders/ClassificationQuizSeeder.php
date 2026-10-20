<?php

namespace Database\Seeders;

use App\Models\Quiz;
use Illuminate\Database\Seeder;

/**
 * Quiz based on the presentation « La Classification des Entreprises »
 * (critères économiques et juridiques). Created closed.
 */
class ClassificationQuizSeeder extends Seeder
{
    private const TITLE = 'La classification des entreprises';

    public function run(): void
    {
        if (Quiz::where('title', self::TITLE)->exists()) {
            return;
        }

        $quiz = Quiz::create([
            'title' => self::TITLE,
            'description' => 'Critères économiques et juridiques — la mission finale d\'Arthur Morgan.',
            'time_per_question' => 20,
            'is_open' => false,
        ]);

        // [question, [choices...], index of the correct choice]
        $questions = [
            ['Quelles sont les deux grandes branches de classification des entreprises ?', ['Critères sociaux et culturels', 'Critères économiques et juridiques', 'Critères géographiques et historiques', 'Critères fiscaux et bancaires'], 1],
            ['À quelle période associe-t-on le développement de la production en grande série ?', ['L\'artisanat', 'L\'essor des services (XXe siècle)', 'L\'industrialisation (XVIIIe – XIXe siècles)', 'L\'ère numérique (XXIe siècle)'], 2],
            ['Quels sont les trois critères économiques de classification ?', ['Secteur d\'activité, taille économique et nature d\'activité', 'Capital, forme juridique et secteur', 'Taille, propriété et chiffre d\'affaires', 'Nature d\'activité, statut et localisation'], 0],
            ['Une entreprise extrait du minerai. À quel secteur appartient-elle ?', ['Secteur secondaire', 'Secteur tertiaire', 'Secteur numérique', 'Secteur primaire'], 3],
            ['Une usine de tissus : à quel secteur appartient-elle ?', ['Secteur primaire', 'Secteur secondaire (transformation)', 'Secteur tertiaire', 'Secteur public'], 1],
            ['Le commerce, le transport, la santé et la banque appartiennent au…', ['Secteur tertiaire (prestation de services)', 'Secteur primaire', 'Secteur secondaire', 'Secteur artisanal'], 0],
            ['Lequel n\'est PAS un indicateur de la taille économique d\'une entreprise ?', ['Le nombre d\'employés', 'Le chiffre d\'affaires', 'L\'âge de l\'entreprise', 'Le total du bilan'], 2],
            ['Quel est l\'ordre de taille, de la plus petite à la plus grande ?', ['PME → TPE → Grande entreprise', 'Grande entreprise → PME → TPE', 'TPE → Grande entreprise → PME', 'TPE → PME → Grande entreprise'], 3],
            ['Un cabinet de conseil fabrique-t-il un bien ou fournit-il un service ?', ['Un bien matériel', 'Un service (prestation immatérielle)', 'Les deux à la fois', 'Ni l\'un ni l\'autre'], 1],
            ['À qui appartient le capital d\'une entreprise publique ?', ['À l\'État ou aux collectivités locales', 'À des investisseurs privés', 'À une famille', 'Uniquement à ses salariés'], 0],
            ['Comment appelle-t-on une entreprise au capital partagé entre l\'État et des investisseurs privés ?', ['Une entreprise privée', 'Une entreprise individuelle', 'Une entreprise publique', 'Une entreprise mixte'], 3],
            ['Quelle structure possède une personnalité juridique distincte (personne morale) ?', ['L\'entreprise individuelle', 'La société (ex : SARL, SA)', 'Le secteur tertiaire', 'La TPE'], 1],
            ['Quelle est la différence majeure de patrimoine entre une entreprise individuelle et une société ?', ['Entreprise individuelle : patrimoines personnel et professionnel liés ; société : patrimoines séparés', 'Entreprise individuelle : patrimoines séparés ; société : patrimoines liés', 'Aucune : le patrimoine est toujours lié', 'Aucune : le patrimoine est toujours séparé'], 0],
            ['Combien de propriétaires une entreprise individuelle a-t-elle ?', ['Au moins deux associés', 'Un seul propriétaire', 'L\'État et des investisseurs privés', 'Au moins sept actionnaires'], 1],
        ];

        foreach ($questions as $q => [$text, $choices, $correct]) {
            $question = $quiz->questions()->create(['text' => $text, 'position' => $q + 1]);

            foreach ($choices as $c => $choiceText) {
                $question->choices()->create([
                    'text' => $choiceText,
                    'is_correct' => $c === $correct,
                    'position' => $c + 1,
                ]);
            }
        }
    }
}
