<?php
/**
 * Reading the Study library.
 *
 * Every method here is a read. The library is written by the harvesters in
 * cli/, never by a visitor, so there is no create/edit/delete on this class
 * on purpose - the only thing a page can do is look.
 */
class Study
{
    /** The three examinations the library covers. */
    public const EXAMS = ['JAMB', 'WAEC', 'NECO'];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * One row per exam: how many papers and questions it holds, and the
     * years it covers.
     */
    public function examSummary(): array
    {
        $sql = "
            SELECT exam,
                   COUNT(*) AS papers,
                   SUM(question_count) AS questions,
                   MIN(year) AS from_year,
                   MAX(year) AS to_year,
                   COUNT(DISTINCT subject_slug) AS subjects
            FROM study_papers
            GROUP BY exam
        ";
        $rows = $this->pdo->query($sql)->fetchAll();

        $out = [];
        foreach (self::EXAMS as $exam) {
            $out[$exam] = [
                'exam' => $exam,
                'papers' => 0,
                'questions' => 0,
                'from_year' => null,
                'to_year' => null,
                'subjects' => 0,
            ];
        }
        foreach ($rows as $row) {
            $out[$row['exam']] = [
                'exam' => $row['exam'],
                'papers' => (int)$row['papers'],
                'questions' => (int)$row['questions'],
                'from_year' => $row['from_year'] !== null ? (int)$row['from_year'] : null,
                'to_year' => $row['to_year'] !== null ? (int)$row['to_year'] : null,
                'subjects' => (int)$row['subjects'],
            ];
        }
        return $out;
    }

    /**
     * The subjects of one exam, with the number of questions each one holds
     * and the span of years it covers.
     */
    public function subjects(string $exam): array
    {
        $stmt = $this->pdo->prepare("
            SELECT subject_slug,
                   MAX(subject_name) AS subject_name,
                   COUNT(*) AS papers,
                   SUM(question_count) AS questions,
                   MIN(year) AS from_year,
                   MAX(year) AS to_year
            FROM study_papers
            WHERE exam = ?
            GROUP BY subject_slug
            ORDER BY subject_name ASC
        ");
        $stmt->execute([$exam]);
        return $stmt->fetchAll();
    }

    /**
     * The years of one subject of one exam, with the number of questions and
     * the number of papers each year holds.
     */
    public function years(string $exam, string $subjectSlug): array
    {
        $stmt = $this->pdo->prepare("
            SELECT year,
                   COUNT(*) AS papers,
                   SUM(question_count) AS questions
            FROM study_papers
            WHERE exam = ? AND subject_slug = ?
            GROUP BY year
            ORDER BY year DESC
        ");
        $stmt->execute([$exam, $subjectSlug]);
        return $stmt->fetchAll();
    }

    /**
     * The papers of one subject and year. A year can hold more than one
     * paper: different sources, and papers from more than one sitting.
     */
    public function papers(string $exam, string $subjectSlug, int $year): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, exam, subject_slug, subject_name, year, paper_code,
                   title, source, source_url, question_count
            FROM study_papers
            WHERE exam = ? AND subject_slug = ? AND year = ?
            ORDER BY source ASC, paper_code ASC
        ");
        $stmt->execute([$exam, $subjectSlug, $year]);
        return $stmt->fetchAll();
    }

    /**
     * Every question of one paper, in order.
     */
    public function questions(int $paperId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT display_number, question, options_json, correct_option,
                   explanation, topic
            FROM study_questions
            WHERE paper_id = ?
            ORDER BY display_number ASC
        ");
        $stmt->execute([$paperId]);
        return $stmt->fetchAll();
    }

    /**
     * The subject name to show for a slug, if we know it.
     */
    public function subjectName(string $exam, string $subjectSlug): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT subject_name FROM study_papers
            WHERE exam = ? AND subject_slug = ?
            LIMIT 1
        ");
        $stmt->execute([$exam, $subjectSlug]);
        $name = $stmt->fetchColumn();
        return $name === false ? null : (string)$name;
    }

    /**
     * The library's slug for a subject the rest of the site knows by name.
     *
     * The old quiz pages call a subject "English Language" or "C.R.S"; the
     * library calls the same subject "english-language" or "crs". This finds
     * the link between them, so a student can move from one to the other.
     */
    public function slugForSubjectName(string $exam, string $subjectName): ?string
    {
        $wanted = strtolower(preg_replace('/[^a-z0-9]+/i', '', $subjectName));
        if ($wanted === '') return null;

        $stmt = $this->pdo->prepare("
            SELECT DISTINCT subject_slug, subject_name FROM study_papers WHERE exam = ?
        ");
        $stmt->execute([$exam]);
        foreach ($stmt->fetchAll() as $row) {
            $candidate = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string)$row['subject_name']));
            if ($candidate === $wanted) return (string)$row['subject_slug'];
        }
        return null;
    }
}
