<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260103112530 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE hockey_matches (id INT AUTO_INCREMENT NOT NULL, home_team_id INT NOT NULL, away_team_id INT NOT NULL, league VARCHAR(255) NOT NULL, match_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', status VARCHAR(50) NOT NULL, home_score_p1 INT DEFAULT NULL, away_score_p1 INT DEFAULT NULL, home_score_p2 INT DEFAULT NULL, away_score_p2 INT DEFAULT NULL, home_score_p3 INT DEFAULT NULL, away_score_p3 INT DEFAULT NULL, home_score_ot INT DEFAULT NULL, away_score_ot INT DEFAULT NULL, home_shootout INT DEFAULT NULL, away_shootout INT DEFAULT NULL, home_final_score INT DEFAULT NULL, away_final_score INT DEFAULT NULL, home_stats JSON DEFAULT NULL, away_stats JSON DEFAULT NULL, head_to_head_stats JSON DEFAULT NULL, venue VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_1707D9239C4C13F6 (home_team_id), INDEX IDX_1707D92345185D02 (away_team_id), INDEX idx_hk_match_date (match_date), INDEX idx_hk_match_league (league), INDEX idx_hk_match_status (status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE hockey_matches ADD CONSTRAINT FK_1707D9239C4C13F6 FOREIGN KEY (home_team_id) REFERENCES teams (id)');
        $this->addSql('ALTER TABLE hockey_matches ADD CONSTRAINT FK_1707D92345185D02 FOREIGN KEY (away_team_id) REFERENCES teams (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE hockey_matches DROP FOREIGN KEY FK_1707D9239C4C13F6');
        $this->addSql('ALTER TABLE hockey_matches DROP FOREIGN KEY FK_1707D92345185D02');
        $this->addSql('DROP TABLE hockey_matches');
    }
}
