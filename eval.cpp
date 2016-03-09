// eval.cpp
// Aswin van Woudenberg & Sander Kastelein

#include "eval.h"
#include "move.h"

#include <tuple>
#include <limits>
#include <algorithm>

int getMonteCarloEval(const State &board, Player player, int trials)
{
    std::vector<Move> moves = board.getMoves();

    int wins = 0;

    for (int i = 0; i < trials; ++i)
    {
        State mcBoard = board;

        std::random_shuffle(moves.begin(), moves.end());
        for (const Move &m: moves) 
            mcBoard.doMove(m);        
        if (mcBoard.getWinner() == player) 
            wins++;
    }
    
    return (2 * wins) - trials;
}

int getRandomEval(const State &board, Player)
{
    return rand() % 101 - 50;
}

