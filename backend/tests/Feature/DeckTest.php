<?php

namespace Tests\Feature;

use App\Enums\DeckVisibility;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Foundation\Testing\TestCase;

class DeckTest extends TestCase
{
    private $deck;
    private $privateDeck;
    private $flashcards;
    private $user;
    private $userPrivate;
    private $user1;
    private $user2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->userPrivate = User::factory()->create();

        $this->deck = Deck::factory()->hasFlashcards(10)->create(
            [
                'id' => 1,
                'name' => 'Test',
                'visibility' => 'Public',
                'likes' => 2,
                'user_id' => $this->user->id,
            ]
        );

        $this->privateDeck = Deck::factory()->hasFlashcards(3)->create(
            [
                'id' => 2,
                'name' => 'TestPrivate',
                'visibility' => 'Private',
                'likes' => 20,
                'user_id' => $this->userPrivate->id,
            ]
        );

        $this->user1 = User::factory()->hasDecks(7)->create();
        $this->user2 = User::factory()->hasDecks(100)->create();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->deck->delete();
        $this->privateDeck->delete();
        $this->user->delete();
        $this->userPrivate->delete();
        $this->user1->delete();
        $this->user2->delete();
    }

    public function test_deck_get_by_page(): void
    {
        $response = $this->getJson('/api/decks');

        $response->assertStatus(200)->assertJsonCount(10, 'decks');

        $response->assertJsonStructure([
            'decks' => [
                '*' => [
                    'id',
                    'name',
                    'visibility',
                    'likes',
                    'flashcards' => [
                        '*' => [
                            'question',
                            'answer'
                        ]
                    ]
                ]
            ],
            'links',
            'meta'
        ]);

        $this->assertEquals($response['meta']['total'], Deck::where('visibility', DeckVisibility::PUBLIC ->value)->count());
    }

    public function test_deck_get_my_decks(): void
    {
        $this->actingAs($this->user1);

        $response = $this->getJson('/api/decks?myDecks');

        $response->assertStatus(200)->assertJsonCount(7, 'decks');
        $this->assertEquals($response['meta']['total'], 7);

        $response->assertJsonStructure([
            'decks' => [
                '*' => [
                    'id',
                    'name',
                    'visibility',
                    'likes',
                    'flashcards' => [
                        '*' => [
                            'question',
                            'answer'
                        ]
                    ]
                ]
            ],
            'links',
            'meta'
        ]);
    }

    public function test_deck_get_my_decks_unauthorized(): void
    {
        $response = $this->getJson('/api/decks?myDecks');

        $response->assertStatus(401)->assertJson([
            'message' => 'Unauthorized'
        ]);
    }

    public function test_deck_get_by_id(): void
    {
        $response = $this->getJson('/api/decks/1');

        $response->assertStatus(200)->assertJson(
            fn(AssertableJson $json) =>
            $json->hasAll(['id', 'name', 'visibility', 'likes', 'flashcards'])
                ->has(
                    'flashcards',
                    fn($json) =>
                    $json->each(
                        fn($json) =>
                        $json->whereType('question', 'string')
                            ->whereType('answer', 'string')
                    )
                )
        );

        $this->assertTrue($response['name'] == 'Test');
        $this->assertTrue($response['visibility'] == 'Public');
        $this->assertTrue($response['likes'] == 2);
        $this->assertTrue(count($response['flashcards']) == 10);

        foreach ($response['flashcards'] as $flashcard) {
            $this->assertTrue($flashcard['question'] != null);
            $this->assertTrue($flashcard['answer'] != null);
        }
    }

    public function test_deck_get_by_id_private(): void
    {
        $this->actingAs($this->userPrivate);

        $response = $this->getJson('/api/decks/2');

        $response->assertStatus(200)->assertJson(
            fn(AssertableJson $json) =>
            $json->hasAll(['id', 'name', 'visibility', 'likes', 'flashcards'])
                ->has(
                    'flashcards',
                    fn($json) =>
                    $json->each(
                        fn($json) =>
                        $json->whereType('question', 'string')
                            ->whereType('answer', 'string')
                    )
                )
        );

        $this->assertTrue($response['name'] == 'TestPrivate');
        $this->assertTrue($response['visibility'] == 'Private');
        $this->assertTrue($response['likes'] == 20);
        $this->assertTrue(count($response['flashcards']) == 3);

        foreach ($response['flashcards'] as $flashcard) {
            $this->assertTrue($flashcard['question'] != null);
            $this->assertTrue($flashcard['answer'] != null);
        }
    }

    public function test_deck_get_by_id_unauthorized(): void
    {
        $response = $this->getJson('/api/decks/2');

        $response->assertStatus(401)->assertJson([
            'message' => 'Unauthorized'
        ]);
    }

    public function test_deck_get_by_id_forbidden(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/decks/2');

        $response->assertStatus(403)->assertJson([
            'message' => 'Forbidden'
        ]);
    }

    public function test_deck_get_by_id_not_found(): void
    {
        $response = $this->getJson('/api/decks/1000');

        $response->assertStatus(404)->assertJson([
            'message' => 'Deck not found'
        ]);
    }

    public function test_deck_can_be_created(): void
    {
        $this->actingAs($this->user);

        $deckData = [
            'name' => 'Test2',
            'visibility' => 'Private',
            'flashcards' => [
                [
                    'question' => 'Question1',
                    'answer' => 'Answer1',
                ],
                [
                    'question' => 'Question2',
                    'answer' => 'Answer2',
                ]
            ]
        ];

        $response = $this->postJson('/api/decks', $deckData, [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]);

        $response->assertStatus(201)->assertJson([
            'message' => 'Deck created successfully'
        ]);

        $this->assertDatabaseHas('decks', [
            'name' => $deckData['name'],
            'visibility' => $deckData['visibility']
        ]);

        $deckId = Deck::where('name', $deckData['name'])->first()->id;

        foreach ($deckData['flashcards'] as $flashcard) {
            $this->assertDatabaseHas('flashcards', [
                'deck_id' => $deckId,
                'question' => $flashcard['question'],
                'answer' => $flashcard['answer']
            ]);
        }
    }

    public function test_deck_creation_unauthorized(): void
    {
        $deckData = [
            'name' => 'Test2',
            'visibility' => 'Private',
            'flashcards' => [
                [
                    'question' => 'Question1',
                    'answer' => 'Answer1',
                ],
                [
                    'question' => 'Question2',
                    'answer' => 'Answer2',
                ]
            ]
        ];

        $response = $this->postJson('/api/decks', $deckData, [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]);

        $response->assertStatus(401)->assertJson([
            'message' => 'Unauthenticated.'
        ]);
    }

    public function test_deck_can_be_updated(): void
    {
        $this->actingAs($this->user);

        $deckData = [
            'name' => 'Test3',
            'visibility' => 'Limited',
            'likes' => 14,
            'flashcards' => [
                [
                    'question' => 'Question3',
                    'answer' => 'Answer3',
                ],
                [
                    'question' => 'Question4',
                    'answer' => 'Answer4',
                ]
            ]
        ];

        $response = $this->putJson('/api/decks/1', $deckData, [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]);

        $this->assertEquals(Deck::find(1)->user_id, $this->user->id);

        $response->assertStatus(204);

        $this->assertDatabaseHas('decks', [
            'name' => $deckData['name'],
            'visibility' => $deckData['visibility']
        ]);

        $this->assertEquals(Deck::where('name', $deckData['name'])->first()->id, 1);

        foreach ($deckData['flashcards'] as $flashcard) {
            $this->assertDatabaseHas('flashcards', [
                'deck_id' => 1,
                'question' => $flashcard['question'],
                'answer' => $flashcard['answer']
            ]);
        }
    }

    public function test_deck_update_unauthorized(): void
    {
        $deckData = [
            'name' => 'Test3',
            'visibility' => 'Limited',
            'likes' => 14,
            'flashcards' => [
                [
                    'question' => 'Question3',
                    'answer' => 'Answer3',
                ],
                [
                    'question' => 'Question4',
                    'answer' => 'Answer4',
                ]
            ]
        ];

        $response = $this->putJson('/api/decks/1', $deckData, [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]);

        $response->assertStatus(401)->assertJson([
            'message' => 'Unauthenticated.'
        ]);
    }

    public function test_deck_update_forbidden(): void
    {
        $this->actingAs($this->user);

        $deckData = [
            'name' => 'Test3',
            'visibility' => 'Limited',
            'likes' => 14,
            'flashcards' => [
                [
                    'question' => 'Question3',
                    'answer' => 'Answer3',
                ],
                [
                    'question' => 'Question4',
                    'answer' => 'Answer4',
                ]
            ]
        ];

        $response = $this->putJson('/api/decks/2', $deckData, [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]);

        $response->assertStatus(403)->assertJson([
            'message' => 'Forbidden'
        ]);
    }

    public function test_deck_can_be_deleted(): void
    {
        $this->actingAs($this->user);

        $flashcards = Deck::find(1)->flashcards();

        $this->assertEquals(Deck::find(1)->user_id, $this->user->id);

        $response = $this->deleteJson('/api/decks/1', [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]);

        $response->assertStatus(204);

        $this->assertDatabaseMissing('decks', [
            'name' => 'Test',
            'id' => 1,
        ]);

        foreach ($flashcards as $flashcard) {
            $this->assertDatabaseMissing('flashcards', [
                'id' => $flashcard->id,
            ]);
        }
    }

    public function test_deck_deletion_unauthorized(): void
    {
        $response = $this->deleteJson('/api/decks/1', [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]);

        $response->assertStatus(401)->assertJson([
            'message' => 'Unauthenticated.'
        ]);
    }

    public function test_deck_deletion_forbidden(): void
    {
        $this->actingAs($this->user);

        $response = $this->deleteJson('/api/decks/2', [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]);

        $response->assertStatus(403)->assertJson([
            'message' => 'Forbidden'
        ]);
    }
}

