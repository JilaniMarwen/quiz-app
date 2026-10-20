<?php

namespace Database\Seeders;

use App\Models\Quiz;
use Illuminate\Database\Seeder;

class DemoQuizSeeder extends Seeder
{
    private const TITLE = "Les bases de l'entrepreneuriat";

    /**
     * Demo quiz: 5 questions, 4 choices each, the correct one flagged. Created closed.
     */
    public function run(): void
    {
        if (Quiz::where('title', self::TITLE)->exists()) {
            return;
        }

        $quiz = Quiz::create([
            'title' => self::TITLE,
            'description' => 'Petit quiz de fin de présentation.',
            'time_per_question' => 20,
            'is_open' => false,
        ]);

        // [question, [choices...], index of the correct choice]
        $questions = [
            ['Que signifie « MVP » ?', ['Most Valuable Player', 'Minimum Viable Product', 'Maximum Value Plan', 'Market Validation Process'], 1],
            ['Quel document présente la stratégie et les prévisions financières d\'un projet ?', ['Le business plan', 'Le bilan comptable', 'Le Kbis', 'La fiche de paie'], 0],
            ['Qu\'est-ce qu\'un « business angel » ?', ['Une banque publique', 'Un client fidèle', 'Un particulier qui investit dans une startup', 'Un incubateur'], 2],
            ['Que représente le « seuil de rentabilité » ?', ['Le prix de vente maximum', 'Le chiffre d\'affaires couvrant toutes les charges', 'Le nombre de salariés minimum', 'Le capital de départ'], 1],
            ['Que décrit le « Business Model Canvas » ?', ['L\'organigramme de l\'entreprise', 'Le plan de communication', 'Les statuts juridiques', 'Comment l\'entreprise crée, délivre et capte de la valeur'], 3],
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
